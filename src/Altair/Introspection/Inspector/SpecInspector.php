<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Result\InspectionTable;

use const DIRECTORY_SEPARATOR;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Walks the host application's spec directory (default: `./api/`) and
 * surfaces the YAML files for `spec:list` and `spec:show`.
 *
 * Parses files directly with `symfony/yaml` rather than going through
 * `Altair\Scaffold\Spec\SpecLoader` so we can list specs whose schemas
 * don't yet pass scaffolder validation — useful when an agent is
 * debugging "why won't this spec scaffold?".
 */
final readonly class SpecInspector
{
    public function __construct(
        private string $specRoot,
    ) {}

    public function inspectAll(): InspectionTable
    {
        if (!is_dir($this->specRoot)) {
            return new InspectionTable(
                title: \sprintf('Specs under %s', $this->specRoot),
                columns: ['path', 'method', 'route'],
                rows: [],
                extras: ['root' => $this->specRoot, 'exists' => false],
            );
        }

        $rows = [];
        foreach ($this->walkYaml($this->specRoot) as $absolute) {
            $rows[] = $this->summariseFile($absolute);
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['path'], $b['path']));

        return new InspectionTable(
            title: \sprintf('Specs under %s', $this->specRoot),
            columns: ['path', 'method', 'route'],
            rows: $rows,
            extras: ['root' => $this->specRoot, 'total' => \count($rows)],
        );
    }

    public function inspectOne(string $path): InspectionTable
    {
        $absolute = $this->resolvePath($path);
        if (!is_file($absolute)) {
            throw new NotFoundException(\sprintf("Spec file not found: %s", $path));
        }

        $parsed = $this->parseYaml($absolute);

        $rows = [];
        $this->flatten('', $parsed, $rows);

        return new InspectionTable(
            title: \sprintf('Spec: %s', $this->relativise($absolute)),
            columns: ['key', 'value'],
            rows: $rows,
            extras: ['absolute_path' => $absolute],
        );
    }

    /**
     * @return iterable<string>
     */
    private function walkYaml(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower((string) $file->getExtension());
            if ($ext === 'yaml' || $ext === 'yml') {
                yield (string) $file;
            }
        }
    }

    /**
     * @return array{ path: string, method: string, route: string }
     */
    private function summariseFile(string $absolute): array
    {
        $relative = $this->relativise($absolute);
        try {
            $parsed = $this->parseYaml($absolute);
        } catch (IntrospectionException) {
            return ['path' => $relative, 'method' => '(parse error)', 'route' => '(parse error)'];
        }

        $endpoint = \is_array($parsed['endpoint'] ?? null) ? $parsed['endpoint'] : [];

        return [
            'path' => $relative,
            'method' => (string) ($endpoint['method'] ?? ''),
            'route' => (string) ($endpoint['path'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $absolute): array
    {
        try {
            $parsed = Yaml::parseFile($absolute);
        } catch (ParseException $parseException) {
            throw new IntrospectionException(\sprintf("Cannot parse YAML '%s': %s", $absolute, $parseException->getMessage()), 0, $parseException);
        }

        return \is_array($parsed) ? $parsed : [];
    }

    /**
     * @param array<string, mixed>          $data
     * @param list<array<string, string>>   $rows
     */
    private function flatten(string $prefix, array $data, array &$rows): void
    {
        foreach ($data as $key => $value) {
            $next = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (\is_array($value)) {
                if ($value === [] || array_is_list($value)) {
                    $rows[] = ['key' => $next, 'value' => (string) json_encode($value, JSON_UNESCAPED_SLASHES)];
                } else {
                    $this->flatten($next, $value, $rows);
                }

                continue;
            }

            $rows[] = ['key' => $next, 'value' => $value === null ? '(null)' : (string) $value];
        }
    }

    private function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        if (str_starts_with($path, $this->specRoot)) {
            return $path;
        }

        return $this->specRoot . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    private function relativise(string $absolute): string
    {
        $prefix = rtrim($this->specRoot, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $prefix)) {
            return substr($absolute, \strlen($prefix));
        }

        return $absolute;
    }
}
