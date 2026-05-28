<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap;

use Altair\Bootstrap\Exception\BootstrapException;

use const DIRECTORY_SEPARATOR;

use FilesystemIterator;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies the skeleton template into a target directory, rewriting the `App`
 * namespace and the composer package name as it goes. This is the file-level
 * heart of `bin/altair new`; the command layers env generation and optional
 * install/verify steps on top.
 */
final class SkeletonGenerator
{
    public static function defaultSkeletonPath(): string
    {
        // Ships inside the package so it travels with univeros/bootstrap when split.
        return __DIR__ . '/resources/skeleton';
    }

    /**
     * @return list<string> the created paths, relative to the target directory
     */
    public function generate(
        string $targetDir,
        ?string $skeletonDir = null,
        string $namespace = 'App',
        string $projectName = 'vendor/app',
        bool $force = false,
    ): array {
        $skeletonDir ??= self::defaultSkeletonPath();
        if (!is_dir($skeletonDir)) {
            throw new BootstrapException(\sprintf("Skeleton template not found at '%s'.", $skeletonDir));
        }

        $namespace = $this->normalizeNamespace($namespace);

        if (is_dir($targetDir) && !$force && $this->isNonEmpty($targetDir)) {
            throw new BootstrapException(\sprintf(
                "Target directory '%s' already exists and is not empty (pass force to overwrite).",
                $targetDir,
            ));
        }

        $created = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($skeletonDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), \strlen($skeletonDir) + 1);
            $target = $targetDir . DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                $this->ensureDir($target);
                continue;
            }

            $this->ensureDir(\dirname($target));
            $contents = $this->transform($relative, (string) file_get_contents($item->getPathname()), $namespace, $projectName);

            if (file_put_contents($target, $contents) === false) {
                throw new BootstrapException(\sprintf("Failed to write '%s'.", $target));
            }

            $created[] = $relative;
        }

        sort($created);

        return $created;
    }

    private function transform(string $relative, string $contents, string $namespace, string $projectName): string
    {
        if ($relative === 'composer.json') {
            return $this->transformComposer($contents, $namespace, $projectName);
        }

        if ($namespace === 'App') {
            return $contents;
        }

        if (str_ends_with($relative, '.php')) {
            $contents = preg_replace('/\bnamespace App\b/', 'namespace ' . $namespace, $contents) ?? $contents;

            return preg_replace('/\bApp\\\\/', $namespace . '\\', $contents) ?? $contents;
        }

        // Non-PHP files (YAML specs, README) reference App\ FQNs as plain text —
        // e.g. api/ping.yaml's `domain.class: App\Health\Ping`.
        return str_replace('App\\', $namespace . '\\', $contents);
    }

    private function transformComposer(string $contents, string $namespace, string $projectName): string
    {
        $data = json_decode($contents, true);
        if (!\is_array($data)) {
            return $contents;
        }

        $data['name'] = $projectName;

        if ($namespace !== 'App') {
            $autoload = $data['autoload'] ?? null;
            $psr4 = \is_array($autoload) ? ($autoload['psr-4'] ?? null) : null;
            if (\is_array($psr4) && \array_key_exists('App\\', $psr4)) {
                $path = $psr4['App\\'];
                unset($psr4['App\\']);
                $psr4[$namespace . '\\'] = $path;
                $autoload['psr-4'] = $psr4;
                $data['autoload'] = $autoload;
            }
        }

        return (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $contents) . "\n";
    }

    private function normalizeNamespace(string $namespace): string
    {
        $namespace = trim($namespace, '\\');
        if ($namespace === '') {
            return 'App';
        }

        foreach (explode('\\', $namespace) as $segment) {
            if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $segment) !== 1) {
                throw new BootstrapException(\sprintf(
                    "'%s' is not a valid PHP namespace; each segment must be a legal identifier.",
                    $namespace,
                ));
            }
        }

        return $namespace;
    }

    private function isNonEmpty(string $dir): bool
    {
        return (new FilesystemIterator($dir))->valid();
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new BootstrapException(\sprintf("Cannot create directory '%s'.", $dir));
        }
    }
}
