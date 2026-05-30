<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Cli;

use Altair\Examples\Cli\IndexCommand;
use Altair\Examples\Cli\ListCommand;
use Altair\Examples\Cli\SearchCommand;
use Altair\Examples\Cli\ShowCommand;
use Altair\Examples\Cli\TestCommand;
use Altair\Examples\Configuration\ExamplesSettings;
use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\IndexBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListCommand::class)]
#[CoversClass(ShowCommand::class)]
#[CoversClass(SearchCommand::class)]
#[CoversClass(IndexCommand::class)]
#[CoversClass(TestCommand::class)]
final class CliCommandsTest extends TestCase
{
    private string $root;
    private ExampleRepository $repository;
    private ExamplesSettings $settings;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-examples-cli-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/http', recursive: true);
        mkdir($this->root . '/persistence', recursive: true);

        file_put_contents($this->root . '/http/basic-endpoint.md', $this->stub(
            'Basic endpoint',
            'The smallest viable endpoint',
            ['http'],
            "Body of the basic endpoint example.",
        ));
        file_put_contents($this->root . '/persistence/crud-repository.md', $this->stub(
            'CRUD repository',
            'Define a repository for one entity',
            ['persistence'],
            "CRUD body.",
        ));

        $this->repository = new ExampleRepository($this->root);
        $this->settings = new ExamplesSettings(
            projectRoot: \dirname($this->root),
            baseDirectory: '.altair',
            libraryDirectory: 'examples',
            indexFileName: 'index.json',
        );
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testListPrintsHumanTable(): void
    {
        $out = $this->capture(fn(): int => (new ListCommand($this->repository))->__invoke());

        self::assertStringContainsString('http/basic-endpoint', $out);
        self::assertStringContainsString('persistence/crud-repository', $out);
        self::assertStringContainsString('Basic endpoint', $out);
    }

    public function testListFiltersByPackage(): void
    {
        $out = $this->capture(fn(): int => (new ListCommand($this->repository))->__invoke(package: 'http'));

        self::assertStringContainsString('http/basic-endpoint', $out);
        self::assertStringNotContainsString('persistence/crud-repository', $out);
    }

    public function testListJsonOutputIsValid(): void
    {
        $out = $this->capture(fn(): int => (new ListCommand($this->repository))->__invoke(format: 'json'));
        $decoded = json_decode(trim($out), true);

        self::assertIsArray($decoded);
        self::assertSame(2, $decoded['count']);
        self::assertCount(2, $decoded['examples']);
    }

    public function testShowRendersOneExample(): void
    {
        $out = $this->capture(fn(): int => (new ShowCommand($this->repository))->__invoke(id: 'http/basic-endpoint'));

        self::assertStringContainsString('# Basic endpoint', $out);
        self::assertStringContainsString('> The smallest viable endpoint', $out);
        self::assertStringContainsString('Body of the basic endpoint example.', $out);
    }

    public function testShowReturnsOneForMissingId(): void
    {
        $exit = 0;
        $out = $this->capture(function () use (&$exit): int {
            return $exit = (new ShowCommand($this->repository))->__invoke(id: 'http/does-not-exist');
        });

        self::assertSame(1, $exit);
        self::assertStringContainsString('No example with id', $out);
    }

    public function testShowEmitsJsonEnvelope(): void
    {
        $out = $this->capture(
            fn(): int => (new ShowCommand($this->repository))->__invoke(id: 'http/basic-endpoint', format: 'json'),
        );
        $decoded = json_decode(trim($out), true);

        self::assertIsArray($decoded);
        self::assertSame('http/basic-endpoint', $decoded['id']);
        self::assertArrayHasKey('body', $decoded);
    }

    public function testSearchFindsMatches(): void
    {
        $out = $this->capture(fn(): int => (new SearchCommand($this->repository))->__invoke(query: 'CRUD'));

        self::assertStringContainsString('persistence/crud-repository', $out);
        self::assertStringContainsString('match', $out);
    }

    public function testSearchHandlesNoMatches(): void
    {
        $out = $this->capture(fn(): int => (new SearchCommand($this->repository))->__invoke(query: 'xyzzy'));

        self::assertStringContainsString("No examples matched 'xyzzy'.", $out);
    }

    public function testIndexCheckFailsWhenIndexMissing(): void
    {
        $settings = new ExamplesSettings(
            projectRoot: $this->root,
            baseDirectory: '.',
            libraryDirectory: '.',
            indexFileName: 'index.json',
        );
        $builder = new IndexBuilder($this->repository);

        $exit = 0;
        $out = $this->capture(function () use ($builder, $settings, &$exit): int {
            return $exit = (new IndexCommand($builder, $settings))->__invoke(check: true);
        });

        self::assertSame(1, $exit);
        self::assertStringContainsString('Index missing', $out);
    }

    public function testIndexWritesThenChecksClean(): void
    {
        $settings = new ExamplesSettings(
            projectRoot: $this->root,
            baseDirectory: '.',
            libraryDirectory: '.',
            indexFileName: 'index.json',
        );
        $builder = new IndexBuilder($this->repository);

        $writeExit = 0;
        $this->capture(function () use ($builder, $settings, &$writeExit): int {
            return $writeExit = (new IndexCommand($builder, $settings))->__invoke();
        });
        self::assertSame(0, $writeExit);
        self::assertFileExists($this->root . '/index.json');

        $checkExit = 0;
        $out = $this->capture(function () use ($builder, $settings, &$checkExit): int {
            return $checkExit = (new IndexCommand($builder, $settings))->__invoke(check: true);
        });
        self::assertSame(0, $checkExit);
        self::assertStringContainsString('Index up to date.', $out);
    }

    public function testTestCommandFailsWhenPhpunitMissing(): void
    {
        $exit = 0;
        $out = $this->capture(function () use (&$exit): int {
            return $exit = (new TestCommand($this->repository, $this->settings))
                ->__invoke(phpunit: '/path/that/does/not/exist');
        });

        self::assertSame(2, $exit);
        self::assertStringContainsString("PHPUnit binary not found", $out);
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

    private function capture(callable $fn): string
    {
        ob_start();
        try {
            $fn();
        } finally {
            $captured = ob_get_clean();
        }

        return (string) $captured;
    }

    private function rmrf(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->rmrf($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
