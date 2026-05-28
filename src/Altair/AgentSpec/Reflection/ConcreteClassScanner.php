<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use Altair\AgentSpec\Contracts\PhpFileFinderInterface;
use Altair\AgentSpec\Model\ClassEntry;
use Altair\AgentSpec\Model\PackageDescriptor;
use ReflectionClass;

/**
 * Scans the package source root for concrete classes — skipping
 * Contracts/, Exception/, Traits/ which are reported in other sections.
 */
class ConcreteClassScanner
{
    private const array SKIP_DIRECTORIES = ['Contracts', 'Exception', 'Traits'];

    public function __construct(
        private readonly PhpFileFinderInterface $finder = new PhpFileFinder(),
        private readonly ClassNameExtractor $extractor = new ClassNameExtractor(),
        private readonly TypeStringRenderer $types = new TypeStringRenderer(),
    ) {}

    /**
     * @return list<ClassEntry>
     */
    public function scan(PackageDescriptor $package): array
    {
        if (!is_dir($package->sourcePath)) {
            return [];
        }

        $entries = [];
        foreach ($this->finder->find($package->sourcePath) as $file) {
            if ($this->isSkipped($file, $package->sourcePath)) {
                continue;
            }

            foreach ($this->extractor->extract($file) as $fqcn) {
                if (!class_exists($fqcn)) {
                    continue;
                }

                $entries[] = $this->describeClass($fqcn, $file, $package);
            }
        }

        usort($entries, static fn(ClassEntry $a, ClassEntry $b): int => strcmp($a->shortName, $b->shortName));

        return $entries;
    }

    private function isSkipped(string $file, string $sourceRoot): bool
    {
        $relative = substr($file, \strlen($sourceRoot) + 1);
        $segment = explode(DIRECTORY_SEPARATOR, $relative)[0] ?? '';

        return \in_array($segment, self::SKIP_DIRECTORIES, true);
    }

    /**
     * @param class-string $fqcn
     */
    private function describeClass(string $fqcn, string $file, PackageDescriptor $package): ClassEntry
    {
        $reflection = new ReflectionClass($fqcn);

        $implements = [];
        foreach ($reflection->getInterfaceNames() as $iface) {
            $implements[$this->types->shortName($iface)] = true;
        }

        $implements = array_keys($implements);
        sort($implements, SORT_STRING);

        $relativePath = $package->relativeSourcePath . '/' . substr($file, \strlen($package->sourcePath) + 1);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        return new ClassEntry(
            fullyQualifiedName: $fqcn,
            shortName: $reflection->getShortName(),
            relativePath: $relativePath,
            isAbstract: $reflection->isAbstract(),
            isFinal: $reflection->isFinal(),
            implements: $implements,
        );
    }
}
