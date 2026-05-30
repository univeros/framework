<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Library;

use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Example;
use Altair\Examples\Library\IndexBuilder;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexBuilder::class)]
final class IndexBuilderTest extends TestCase
{
    public function testBuildReturnsDeterministicJson(): void
    {
        $repo = new InMemoryExampleRepository([
            new Example(
                id: 'http/basic',
                title: 'A',
                scenario: 'one',
                packages: ['http'],
                since: '2.0.0',
                testedBy: 'tests/Examples/Http/BasicTest.php',
                body: 'body',
            ),
            new Example(
                id: 'persistence/crud',
                title: 'B',
                scenario: 'two',
                packages: ['persistence'],
                since: '2.0.0',
                testedBy: 'tests/Examples/Persistence/CrudTest.php',
                body: 'body',
            ),
        ]);

        $first = (new IndexBuilder($repo))->build();
        $second = (new IndexBuilder($repo))->build();

        self::assertSame($first, $second, 'identical input must produce identical output');
        self::assertStringEndsWith("\n", $first, 'trailing newline keeps the file unix-clean');

        $decoded = json_decode($first, true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['version']);
        self::assertSame(2, $decoded['count']);
        self::assertSame('http/basic', $decoded['examples'][0]['id']);
        self::assertSame('persistence/crud', $decoded['examples'][1]['id']);
        self::assertArrayNotHasKey('body', $decoded['examples'][0], 'body must not leak into the index');
    }

    public function testWriteToCreatesFileAtomically(): void
    {
        $repo = new InMemoryExampleRepository([
            new Example(
                id: 'a/b',
                title: 'A',
                scenario: 'one',
                packages: ['http'],
                since: '2.0.0',
                testedBy: 'tests/Examples/AB.php',
                body: 'body',
            ),
        ]);

        $tmp = sys_get_temp_dir() . '/altair-index-' . bin2hex(random_bytes(4)) . '/index.json';

        try {
            (new IndexBuilder($repo))->writeTo($tmp);

            self::assertFileExists($tmp);
            $contents = (string) file_get_contents($tmp);
            self::assertJson($contents);
            self::assertStringContainsString('"id": "a/b"', $contents);

            $glob = glob(\dirname($tmp) . '/*.tmp.*') ?: [];
            self::assertSame([], $glob, 'no temp files should remain after a successful write');
        } finally {
            @unlink($tmp);
            @rmdir(\dirname($tmp));
        }
    }
}

/**
 * @internal
 */
final readonly class InMemoryExampleRepository implements ExampleRepositoryInterface
{
    /**
     * @param list<Example> $examples
     */
    public function __construct(private array $examples) {}

    #[Override]
    public function findAll(): array
    {
        return $this->examples;
    }

    #[Override]
    public function findById(string $id): Example
    {
        foreach ($this->examples as $example) {
            if ($example->id === $id) {
                return $example;
            }
        }

        throw new \RuntimeException('not found: ' . $id);
    }

    #[Override]
    public function findByPackage(string $package): array
    {
        return array_values(array_filter(
            $this->examples,
            static fn(Example $e): bool => \in_array($package, $e->packages, true),
        ));
    }

    #[Override]
    public function search(string $query): array
    {
        return [];
    }
}
