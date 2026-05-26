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
use Altair\AgentSpec\Model\ContractEntry;
use Altair\AgentSpec\Model\MethodSignature;
use Altair\AgentSpec\Model\PackageDescriptor;
use ReflectionClass;
use ReflectionMethod;

/**
 * Scans `<package>/Contracts/*.php` and reflects on every discovered interface.
 */
class ContractScanner
{
    public function __construct(
        private readonly PhpFileFinderInterface $finder = new PhpFileFinder(),
        private readonly ClassNameExtractor $extractor = new ClassNameExtractor(),
        private readonly TypeStringRenderer $types = new TypeStringRenderer(),
    ) {}

    /**
     * @return list<ContractEntry>
     */
    public function scan(PackageDescriptor $package): array
    {
        $contractsPath = $package->sourcePath . DIRECTORY_SEPARATOR . 'Contracts';
        if (!is_dir($contractsPath)) {
            return [];
        }

        $entries = [];
        foreach ($this->finder->find($contractsPath) as $file) {
            foreach ($this->extractor->extract($file) as $fqcn) {
                if (!interface_exists($fqcn)) {
                    continue;
                }

                $entries[] = $this->describeInterface($fqcn);
            }
        }

        usort($entries, static fn(ContractEntry $a, ContractEntry $b): int => strcmp($a->shortName, $b->shortName));

        return $entries;
    }

    private function describeInterface(string $fqcn): ContractEntry
    {
        $reflection = new ReflectionClass($fqcn);
        $methods = [];

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $methods[] = $this->describeMethod($method);
        }

        usort($methods, static fn(MethodSignature $a, MethodSignature $b): int => strcmp($a->name, $b->name));

        $extends = [];
        foreach ($reflection->getInterfaceNames() as $parent) {
            $extends[] = $this->types->shortName($parent);
        }

        sort($extends, SORT_STRING);

        $constants = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $constants[] = $constant->getName();
        }

        sort($constants, SORT_STRING);

        return new ContractEntry(
            fullyQualifiedName: $fqcn,
            shortName: $reflection->getShortName(),
            methods: $methods,
            extends: $extends,
            constants: $constants,
        );
    }

    private function describeMethod(ReflectionMethod $method): MethodSignature
    {
        $parameterTypes = [];
        foreach ($method->getParameters() as $parameter) {
            $parameterTypes[] = $this->types->render($parameter->getType());
        }

        return new MethodSignature(
            name: $method->getName(),
            parameterTypes: $parameterTypes,
            returnType: $this->types->render($method->getReturnType()),
        );
    }
}
