<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Determinism;

use FilesystemIterator;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Reusable test harness for the #74 determinism gate.
 *
 * For each test, the harness creates two sibling temp directories, hands them
 * to a caller-supplied action (which runs the emitter targeting each
 * directory in turn), and then asserts that the resulting trees — or files —
 * are byte-identical.
 *
 * Comparing both the file set (so a missing file is caught) and each file's
 * raw bytes (so a one-character timestamp drift is caught) is what makes
 * this an honest enforcement of the "byte-stable across runs" standard.
 */
final readonly class TwiceHarness
{
    private string $base;

    public function __construct(string $prefix)
    {
        $this->base = sys_get_temp_dir() . '/' . $prefix . '-' . bin2hex(random_bytes(5));
        mkdir($this->base . '/first', 0o755, true);
        mkdir($this->base . '/second', 0o755, true);
    }

    /**
     * Invoke the action twice — once per output directory — and return both
     * paths so the caller can diff them.
     *
     * @param callable(string): void $action
     *
     * @return array{0: string, 1: string}
     */
    public function pair(callable $action): array
    {
        $first = $this->base . '/first';
        $second = $this->base . '/second';

        $action($first);
        $action($second);

        return [$first, $second];
    }

    public function assertTreesByteEqual(string $first, string $second): void
    {
        $a = $this->listFiles($first);
        $b = $this->listFiles($second);

        Assert::assertSame($a, $b, 'emitter produced different file sets across runs');

        foreach ($a as $relative) {
            $this->assertFilesByteEqual($first . '/' . $relative, $second . '/' . $relative);
        }
    }

    public function assertFilesByteEqual(string $first, string $second): void
    {
        $a = (string) file_get_contents($first);
        $b = (string) file_get_contents($second);

        if ($a === $b) {
            Assert::assertTrue(true);

            return;
        }

        Assert::fail(\sprintf(
            "Emitter output differs between runs (non-deterministic):\n  first:  %s (%d bytes)\n  second: %s (%d bytes)\n  diff bytes: %d",
            $first,
            \strlen($a),
            $second,
            \strlen($b),
            \strlen($a) - \strlen($b),
        ));
    }

    public function cleanup(): void
    {
        if (!is_dir($this->base)) {
            return;
        }

        $this->rrmdir($this->base);
    }

    /**
     * @return list<string> file paths relative to $root, sorted alphabetically
     */
    private function listFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $files = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen($root) + 1);
            $files[] = str_replace('\\', '/', $relative);
        }

        sort($files);

        return $files;
    }

    private function rrmdir(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($dir);
    }
}
