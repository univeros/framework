<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use Altair\AgentSpec\Contracts\PackageScannerInterface;
use Altair\AgentSpec\Exception\AgentSpecException;
use Altair\AgentSpec\Model\PackageDescriptor;
use FilesystemIterator;
use Override;

/**
 * Discovers sub-packages by looking for composer.json files inside the
 * monorepo source root. Each sub-package directory becomes a PackageDescriptor.
 */
class PackageScanner implements PackageScannerInterface
{
    #[Override]
    public function scan(string $sourceRoot, string $monorepoRoot, ?string $testsRoot): array
    {
        if (!is_dir($sourceRoot)) {
            throw new AgentSpecException(\sprintf("Source root '%s' is not a directory.", $sourceRoot));
        }

        $descriptors = [];
        foreach (new FilesystemIterator($sourceRoot, FilesystemIterator::SKIP_DOTS) as $entry) {
            if (!$entry->isDir()) {
                continue;
            }

            $manifestPath = $entry->getPathname() . DIRECTORY_SEPARATOR . 'composer.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $descriptors[] = $this->describePackage(
                $entry->getPathname(),
                $manifestPath,
                $monorepoRoot,
                $testsRoot,
            );
        }

        usort($descriptors, static fn(PackageDescriptor $a, PackageDescriptor $b): int => strcmp($a->packageName, $b->packageName));

        return $descriptors;
    }

    private function describePackage(
        string $packagePath,
        string $manifestPath,
        string $monorepoRoot,
        ?string $testsRoot,
    ): PackageDescriptor {
        $manifest = $this->loadJson($manifestPath);

        $packageName = $this->stringOrThrow($manifest, 'name', $manifestPath);
        $description = (string) ($manifest['description'] ?? '');
        $rootNamespace = $this->resolveRootNamespace($manifest, $manifestPath);
        $slug = $this->extractSlug($packageName);
        $required = $this->resolveRequiredPackages($manifest);

        $relativeSource = $this->makeRelative($packagePath, $monorepoRoot);

        $testsPath = null;
        $relativeTests = null;
        if ($testsRoot !== null) {
            $candidate = $testsRoot . DIRECTORY_SEPARATOR . basename($packagePath);
            if (is_dir($candidate)) {
                $testsPath = $candidate;
                $relativeTests = $this->makeRelative($candidate, $monorepoRoot);
            }
        }

        return new PackageDescriptor(
            packageName: $packageName,
            description: $description,
            rootNamespace: $rootNamespace,
            sourcePath: $packagePath,
            relativeSourcePath: $relativeSource,
            testsPath: $testsPath,
            relativeTestsPath: $relativeTests,
            manifestSlug: $slug,
            requiredPackages: $required,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function loadJson(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new AgentSpecException(\sprintf("Cannot read '%s'.", $path));
        }

        $decoded = json_decode($raw, true);
        if (!\is_array($decoded)) {
            throw new AgentSpecException(\sprintf("'%s' is not a valid JSON object.", $path));
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function stringOrThrow(array $manifest, string $key, string $path): string
    {
        $value = $manifest[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            throw new AgentSpecException(\sprintf("'%s' is missing required string key '%s'.", $path, $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function resolveRootNamespace(array $manifest, string $path): string
    {
        $autoload = $manifest['autoload']['psr-4'] ?? null;
        if (!\is_array($autoload) || $autoload === []) {
            throw new AgentSpecException(\sprintf("'%s' has no autoload.psr-4 entries.", $path));
        }

        $first = array_key_first($autoload);

        return rtrim((string) $first, '\\');
    }

    private function extractSlug(string $packageName): string
    {
        $parts = explode('/', $packageName);

        return end($parts) ?: $packageName;
    }

    /**
     * @param  array<string, mixed> $manifest
     * @return list<string>
     */
    private function resolveRequiredPackages(array $manifest): array
    {
        $require = $manifest['require'] ?? [];
        if (!\is_array($require)) {
            return [];
        }

        $names = [];
        foreach (array_keys($require) as $name) {
            if (!\is_string($name)) {
                continue;
            }
            if ($name === 'php' || str_starts_with($name, 'ext-')) {
                continue;
            }

            $names[] = $name;
        }

        sort($names, SORT_STRING);

        return $names;
    }

    private function makeRelative(string $path, string $root): string
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if ($root === '' || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return substr($path, \strlen($root) + 1);
    }
}
