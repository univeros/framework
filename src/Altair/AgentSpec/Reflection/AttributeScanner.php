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
use Altair\AgentSpec\Model\AttributeConvention;
use Altair\AgentSpec\Model\PackageDescriptor;
use ReflectionClass;

/**
 * Discovers ATTRIBUTE_* class constants — the framework's convention for
 * advertising PSR-7 request-attribute keys (e.g. `altair:http:ip-address`).
 */
class AttributeScanner
{
    private const string PREFIX = 'ATTRIBUTE_';

    public function __construct(
        private readonly PhpFileFinderInterface $finder = new PhpFileFinder(),
        private readonly ClassNameExtractor $extractor = new ClassNameExtractor(),
    ) {}

    /**
     * @return list<AttributeConvention>
     */
    public function scan(PackageDescriptor $package): array
    {
        $conventions = [];

        foreach ($this->finder->find($package->sourcePath) as $file) {
            foreach ($this->extractor->extract($file) as $fqcn) {
                if (!class_exists($fqcn) && !interface_exists($fqcn)) {
                    continue;
                }

                foreach ($this->collect($fqcn) as $convention) {
                    $conventions[] = $convention;
                }
            }
        }

        $conventions = $this->deduplicate($conventions);
        usort(
            $conventions,
            static fn(AttributeConvention $a, AttributeConvention $b): int => strcmp($a->value, $b->value)
                ?: strcmp($a->constantName, $b->constantName),
        );

        return $conventions;
    }

    /**
     * @param  class-string             $fqcn
     * @return list<AttributeConvention>
     */
    private function collect(string $fqcn): array
    {
        $reflection = new ReflectionClass($fqcn);
        $results = [];

        foreach ($reflection->getReflectionConstants() as $constant) {
            $name = $constant->getName();
            if (!str_starts_with($name, self::PREFIX)) {
                continue;
            }

            $value = $constant->getValue();
            if (!\is_string($value)) {
                continue;
            }

            if ($constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $shortName = $reflection->getShortName();

            $results[] = new AttributeConvention(
                constantName: $name,
                value: $value,
                declaringClassShortName: $shortName,
                declaringClassFqcn: $fqcn,
            );
        }

        return $results;
    }

    /**
     * @param  list<AttributeConvention> $conventions
     * @return list<AttributeConvention>
     */
    private function deduplicate(array $conventions): array
    {
        $seen = [];
        $unique = [];
        foreach ($conventions as $convention) {
            $key = $convention->declaringClassFqcn . '::' . $convention->constantName;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $convention;
        }

        return $unique;
    }
}
