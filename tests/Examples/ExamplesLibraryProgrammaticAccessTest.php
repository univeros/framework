<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples;

use Altair\Examples\Library\Example;
use Altair\Examples\Library\ExampleParser;
use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\IndexBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Runs the snippet from .altair/examples/examples-library/programmatic-access.md
 * end-to-end. Asserts that ExampleRepository walks a directory, finds entries,
 * filters by package, searches, and that IndexBuilder writes a JSON file.
 */
final class ExamplesLibraryProgrammaticAccessTest extends TestCase
{
    private string $libraryRoot;

    protected function setUp(): void
    {
        $this->libraryRoot = sys_get_temp_dir() . '/altair-examples-prog-' . bin2hex(random_bytes(4));
        mkdir($this->libraryRoot . '/http', recursive: true);
        mkdir($this->libraryRoot . '/persistence', recursive: true);

        file_put_contents($this->libraryRoot . '/http/basic.md', $this->stub(
            'Basic HTTP endpoint',
            'Smallest viable endpoint',
            ['http'],
            'Body of the HTTP example.',
        ));
        file_put_contents($this->libraryRoot . '/persistence/outbox.md', $this->stub(
            'Outbox pattern',
            'Atomic side effects via outbox transport',
            ['persistence', 'messaging'],
            'Outbox body talks about outbox.',
        ));
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->libraryRoot);
    }

    public function testWalksTheLibraryAndExposesEntries(): void
    {
        $repository = new ExampleRepository($this->libraryRoot, new ExampleParser());

        $ids = array_map(static fn(Example $e): string => $e->id, $repository->findAll());

        self::assertSame(['http/basic', 'persistence/outbox'], $ids);
    }

    public function testFilterAndSearchAndFindByIdWorkTogether(): void
    {
        $repository = new ExampleRepository($this->libraryRoot, new ExampleParser());

        $httpOnly = array_map(static fn(Example $e): string => $e->id, $repository->findByPackage('http'));
        self::assertSame(['http/basic'], $httpOnly);

        $outbox = array_map(static fn(Example $e): string => $e->id, $repository->search('outbox'));
        self::assertSame(['persistence/outbox'], $outbox);

        self::assertSame('Outbox pattern', $repository->findById('persistence/outbox')->title);
    }

    public function testIndexBuilderWritesAReadableJsonFile(): void
    {
        $repository = new ExampleRepository($this->libraryRoot, new ExampleParser());
        $indexPath = $this->libraryRoot . '/index.json';

        (new IndexBuilder($repository))->writeTo($indexPath);

        self::assertFileExists($indexPath);
        $decoded = json_decode((string) file_get_contents($indexPath), true);
        self::assertIsArray($decoded);
        self::assertSame(2, $decoded['count']);
        self::assertSame('http/basic', $decoded['examples'][0]['id']);
    }

    /**
     * @param list<string> $packages
     */
    private function stub(string $title, string $scenario, array $packages, string $body): string
    {
        $packagesYaml = '[' . implode(', ', $packages) . ']';

        return <<<MD
        ---
        title: {$title}
        scenario: {$scenario}
        packages: {$packagesYaml}
        since: 2.0.0
        tested_by: tests/Examples/Stub.php
        ---
        {$body}
        MD;
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->rmrf($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
