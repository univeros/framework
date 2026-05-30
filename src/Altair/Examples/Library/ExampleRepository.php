<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library;

use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\Exception\ExampleNotFoundException;
use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Filesystem-backed example repository. Walks `<libraryRoot>/**\/*.md`, parses
 * each file via {@see ExampleParser}, and caches the result for the lifetime of
 * the instance (the library is small and lookups are read-only — a single eager
 * pass is simpler than incremental indexing here).
 *
 * `index.json` files are deliberately skipped — that's the generated index, not
 * an example.
 */
final class ExampleRepository implements ExampleRepositoryInterface
{
    /**
     * @var list<Example>|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly string $libraryRoot,
        private readonly ExampleParser $parser = new ExampleParser(),
    ) {}

    #[Override]
    public function findAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!is_dir($this->libraryRoot)) {
            return $this->cache = [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->libraryRoot, FilesystemIterator::SKIP_DOTS),
        );

        $examples = [];
        foreach ($iterator as $fileInfo) {
            \assert($fileInfo instanceof SplFileInfo);
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getExtension() !== 'md') {
                continue;
            }

            $absolute = $fileInfo->getPathname();
            $id = $this->idFromPath($absolute);
            $source = (string) file_get_contents($absolute);
            $examples[] = $this->parser->parse($id, $source);
        }

        usort($examples, static fn(Example $a, Example $b): int => $a->id <=> $b->id);

        return $this->cache = $examples;
    }

    #[Override]
    public function findById(string $id): Example
    {
        foreach ($this->findAll() as $example) {
            if ($example->id === $id) {
                return $example;
            }
        }

        throw ExampleNotFoundException::id($id);
    }

    #[Override]
    public function findByPackage(string $package): array
    {
        return array_values(array_filter(
            $this->findAll(),
            static fn(Example $example): bool => \in_array($package, $example->packages, true),
        ));
    }

    #[Override]
    public function search(string $query): array
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return [];
        }

        return array_values(array_filter(
            $this->findAll(),
            static function (Example $example) use ($needle): bool {
                $haystack = mb_strtolower(
                    $example->id . "\n" . $example->title . "\n" . $example->scenario . "\n" . $example->body,
                );

                return str_contains($haystack, $needle);
            },
        ));
    }

    private function idFromPath(string $absolute): string
    {
        $prefix = rtrim($this->libraryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $relative = str_starts_with($absolute, $prefix) ? substr($absolute, \strlen($prefix)) : $absolute;
        $relative = (string) preg_replace('/\.md$/u', '', $relative);

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
