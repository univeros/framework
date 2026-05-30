<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Library;

use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\Exception\ExampleNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleRepository::class)]
final class ExampleRepositoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-examples-test-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/http', recursive: true);
        mkdir($this->root . '/persistence', recursive: true);

        file_put_contents($this->root . '/http/basic-endpoint.md', $this->stub(
            title: 'Basic endpoint',
            scenario: 'The smallest viable endpoint',
            packages: ['http'],
            body: "Hello from HTTP basic endpoint.",
        ));

        file_put_contents($this->root . '/http/endpoint-with-auth.md', $this->stub(
            title: 'Endpoint with JWT auth',
            scenario: 'Wire a JWT auth gate in front of an endpoint',
            packages: ['http', 'security'],
            body: "Hello with auth.",
        ));

        file_put_contents($this->root . '/persistence/crud-repository.md', $this->stub(
            title: 'CRUD repository',
            scenario: 'Define a repository for a single entity',
            packages: ['persistence'],
            body: "CRUD body.",
        ));

        // Stray non-md file should be ignored
        file_put_contents($this->root . '/http/not-an-example.txt', 'ignore me');
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->root);
    }

    public function testFindAllReturnsEveryExampleSortedById(): void
    {
        $repo = new ExampleRepository($this->root);

        $ids = array_map(static fn($e) => $e->id, $repo->findAll());

        self::assertSame([
            'http/basic-endpoint',
            'http/endpoint-with-auth',
            'persistence/crud-repository',
        ], $ids);
    }

    public function testFindByIdReturnsTheRightExample(): void
    {
        $repo = new ExampleRepository($this->root);

        $example = $repo->findById('http/endpoint-with-auth');

        self::assertSame('Endpoint with JWT auth', $example->title);
        self::assertSame(['http', 'security'], $example->packages);
    }

    public function testFindByIdThrowsWhenMissing(): void
    {
        $repo = new ExampleRepository($this->root);

        $this->expectException(ExampleNotFoundException::class);
        $this->expectExceptionMessage('No example with id "http/does-not-exist"');

        $repo->findById('http/does-not-exist');
    }

    public function testFindByPackageFiltersOnTheList(): void
    {
        $repo = new ExampleRepository($this->root);

        $ids = array_map(static fn($e) => $e->id, $repo->findByPackage('http'));
        sort($ids);

        self::assertSame(['http/basic-endpoint', 'http/endpoint-with-auth'], $ids);
        self::assertSame(['persistence/crud-repository'], array_map(
            static fn($e) => $e->id,
            $repo->findByPackage('persistence'),
        ));
        self::assertSame([], $repo->findByPackage('does-not-exist'));
    }

    public function testSearchIsCaseInsensitiveAcrossAllFields(): void
    {
        $repo = new ExampleRepository($this->root);

        self::assertCount(1, $repo->search('CRUD'));
        self::assertCount(1, $repo->search('crud'));            // case-insensitive
        self::assertCount(2, $repo->search('endpoint'));        // matches title
        self::assertCount(1, $repo->search('jwt'));             // matches scenario
        self::assertCount(1, $repo->search('Hello from HTTP')); // matches body
        self::assertSame([], $repo->search('   '));             // blank → empty
    }

    public function testHandlesMissingLibraryRootGracefully(): void
    {
        $repo = new ExampleRepository($this->root . '/does-not-exist');

        self::assertSame([], $repo->findAll());
        self::assertSame([], $repo->findByPackage('http'));
        self::assertSame([], $repo->search('anything'));
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
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->rmrf($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
