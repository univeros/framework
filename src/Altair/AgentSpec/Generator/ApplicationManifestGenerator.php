<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Generator;

use Altair\AgentSpec\Contracts\PhpFileFinderInterface;
use Altair\AgentSpec\Reflection\ClassNameExtractor;
use Altair\AgentSpec\Reflection\PhpFileFinder;
use ReflectionClass;

/**
 * Walks user-application source paths and emits a single Markdown manifest
 * listing classes that opt into the framework's published attribute markers
 * (CLI commands, HTTP actions, event handlers, jobs).
 *
 * This is a v1 stub: it groups classes by the short name of the attribute
 * they carry. Richer per-attribute renderers can be added incrementally
 * once #1, #3, and #5 land.
 */
class ApplicationManifestGenerator
{
    /**
     * @param list<class-string> $markerAttributes
     */
    public function __construct(
        private readonly array $markerAttributes,
        private readonly PhpFileFinderInterface $finder = new PhpFileFinder(),
        private readonly ClassNameExtractor $extractor = new ClassNameExtractor(),
    ) {}

    /**
     * @param list<string> $paths Application source directories.
     */
    public function render(array $paths): string
    {
        $buckets = [];
        foreach ($paths as $path) {
            foreach ($this->finder->find($path) as $file) {
                foreach ($this->extractor->extract($file) as $fqcn) {
                    if (!class_exists($fqcn)) {
                        continue;
                    }

                    foreach ($this->matchedAttributes($fqcn) as $attribute) {
                        $buckets[$attribute][] = $fqcn;
                    }
                }
            }
        }

        ksort($buckets, SORT_STRING);

        $body = ['# Application — Agent Manifest', '', 'Classes in the host application that carry framework attribute markers.', ''];

        if ($buckets === []) {
            $body[] = '_(no marker-attributed classes found)_';

            return implode("\n", $body) . "\n";
        }

        foreach ($buckets as $attribute => $classes) {
            $classes = array_values(array_unique($classes));
            sort($classes, SORT_STRING);
            $body[] = \sprintf('## %s', $this->shortName($attribute));
            $body[] = '';
            foreach ($classes as $class) {
                $body[] = \sprintf('- `%s`', $class);
            }

            $body[] = '';
        }

        return rtrim(implode("\n", $body), "\n") . "\n";
    }

    /**
     * @return list<class-string>
     */
    private function matchedAttributes(string $fqcn): array
    {
        $reflection = new ReflectionClass($fqcn);
        $matched = [];
        foreach ($this->markerAttributes as $attribute) {
            if ($reflection->getAttributes($attribute) !== []) {
                $matched[] = $attribute;
            }
        }

        return $matched;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}
