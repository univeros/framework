<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Container\Container;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Result\InspectionTable;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Walks the Container's six binding collections without triggering
 * instantiation — so it's safe to run against a project whose database
 * is down or whose Configuration `prepare` hooks would have side effects.
 *
 * `inspectAll()` / `inspectOne()` report definitions (what *would* be
 * built). `inspectRealized()` reports the complementary view — instances
 * the Container has actually constructed so far — for long-running-process
 * introspection (worker memory growth, surprised-by-singleton, prepare-hook
 * ordering). It still never instantiates: it only reads the already-built
 * objects sitting in the shares collection.
 */
final readonly class ContainerInspector
{
    public function __construct(
        private Container $container,
    ) {}

    /**
     * Full binding inventory, one row per binding.
     *
     * Filters:
     * - $sharedOnly  → restrict to bindings registered as singletons.
     * - $filter      → case-insensitive substring match on the binding name.
     */
    public function inspectAll(bool $sharedOnly = false, ?string $filter = null): InspectionTable
    {
        $rows = [];
        $needle = $filter !== null && $filter !== '' ? strtolower($filter) : null;

        foreach ($this->collectBindings() as $row) {
            if ($sharedOnly && !$row['shared']) {
                continue;
            }

            if ($needle !== null && !str_contains(strtolower($row['id']), $needle)) {
                continue;
            }

            $rows[] = $row;
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

        return new InspectionTable(
            title: 'Container bindings',
            columns: ['id', 'kind', 'target', 'shared'],
            rows: $rows,
            extras: ['total' => \count($rows)],
        );
    }

    /**
     * Realised view: only the singletons the Container has actually
     * constructed so far.
     *
     * The shares collection holds a `null` placeholder for a singleton
     * that has been registered but not yet built (see
     * `SharesCollection::shareClass()`), and the constructed object once
     * `make()` (or `share($instance)`) has run. So a non-null value is the
     * "has been instantiated" signal — we filter on exactly that.
     *
     * Safe by construction: we read instances that already exist and only
     * call `::class` on them. We never `make()`, and never serialise (which
     * would fire `__sleep` / `__serialize` side effects).
     *
     * @param string|null $filter Case-insensitive substring match on the binding name.
     */
    public function inspectRealized(?string $filter = null): InspectionTable
    {
        $needle = $filter !== null && $filter !== '' ? strtolower($filter) : null;
        $rows = [];

        foreach ($this->container->getShares() as $name => $instance) {
            if (!\is_object($instance)) {
                continue; // null placeholder — registered but not yet built.
            }

            $id = $this->displayName((string) $name);

            if ($needle !== null && !str_contains(strtolower($id), $needle)) {
                continue;
            }

            $rows[] = [
                'id' => $id,
                'kind' => 'share',
                'class' => $instance::class,
            ];
        }

        usort($rows, static fn(array $a, array $b): int => strcmp($a['id'], $b['id']));

        return new InspectionTable(
            title: 'Realised container services',
            columns: ['id', 'kind', 'class'],
            rows: $rows,
            extras: ['total' => \count($rows)],
        );
    }

    /**
     * Detail view for one binding.
     *
     * Includes constructor dependencies via reflection — safe because
     * we reflect, never instantiate.
     */
    public function inspectOne(string $id): InspectionTable
    {
        $aliases = $this->container->getAliases();
        $shares = $this->container->getShares();
        $delegates = $this->container->getDelegates();
        $prepares = $this->container->getPrepares();

        // Container collections are keyed by lower-cased class names — match the same normalization.
        $lookupKey = strtolower(ltrim($id, '\\'));
        $aliasTarget = isset($aliases[$lookupKey]) ? (string) $aliases[$lookupKey] : null;
        $resolved = $aliasTarget ?? ltrim($id, '\\');

        $kind = match (true) {
            $aliasTarget !== null => 'alias',
            $delegates->hasKey($lookupKey) => 'delegate',
            $shares->hasKey($lookupKey) => 'share',
            class_exists($resolved) || interface_exists($resolved) => 'class',
            default => throw new NotFoundException(\sprintf("No binding for '%s'.", $id)),
        };

        $rows = [
            ['field' => 'id', 'value' => $id],
            ['field' => 'kind', 'value' => $kind],
            ['field' => 'target', 'value' => $resolved],
            ['field' => 'shared', 'value' => $shares->hasKey($lookupKey) ? 'true' : 'false'],
        ];

        if ($prepares->hasKey($lookupKey)) {
            $rows[] = ['field' => 'prepares', 'value' => $this->describeCallable($prepares[$lookupKey])];
        }

        foreach ($this->dependenciesFor($resolved) as $position => $dep) {
            $rows[] = [
                'field' => \sprintf('dep[%d]', $position),
                'value' => \sprintf('$%s : %s', $dep['name'], $dep['type']),
            ];
        }

        return new InspectionTable(
            title: \sprintf('Container binding: %s', $id),
            columns: ['field', 'value'],
            rows: $rows,
        );
    }

    /**
     * Underlying enumeration used by `inspectAll()` — exposed so callers
     * (and tests) can walk the raw row stream without re-applying filters.
     *
     * @return iterable<array{ id: string, kind: string, target: string, shared: bool }>
     */
    public function collectBindings(): iterable
    {
        $aliases = $this->container->getAliases();
        $shares = $this->container->getShares();
        $delegates = $this->container->getDelegates();
        $classDefinitions = $this->container->getClassDefinitions();
        $params = $this->container->getParameterDefinitions();

        $seen = [];

        foreach ($aliases as $original => $target) {
            $normalized = (string) $original;
            $seen[$normalized] = true;
            // An alias's `shared` flag reflects whether the alias name
            // itself is registered as a singleton — which is uncommon.
            // The target's share status appears on its own row. Use
            // `hasKey()` (not `isset()`) because `Map::offsetExists()` is
            // value-aware and `shareClass()` stores a null placeholder
            // until the instance is first constructed.
            yield [
                'id' => $this->displayName($normalized),
                'kind' => 'alias',
                'target' => (string) $target,
                'shared' => $shares->hasKey($normalized),
            ];
        }

        foreach ($shares as $name => $_) {
            $normalized = (string) $name;
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $display = $this->displayName($normalized);
            yield [
                'id' => $display,
                'kind' => 'share',
                'target' => $display,
                'shared' => true,
            ];
        }

        foreach ($delegates as $name => $_) {
            $normalized = (string) $name;
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $display = $this->displayName($normalized);
            yield [
                'id' => $display,
                'kind' => 'delegate',
                'target' => $display,
                'shared' => $shares->hasKey($normalized),
            ];
        }

        foreach ($classDefinitions as $name => $_) {
            $normalized = (string) $name;
            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $display = $this->displayName($normalized);
            yield [
                'id' => $display,
                'kind' => 'definition',
                'target' => $display,
                'shared' => $shares->hasKey($normalized),
            ];
        }

        foreach ($params as $name => $value) {
            $id = '$' . $name;
            yield [
                'id' => $id,
                'kind' => 'parameter',
                'target' => $this->describeValue($value),
                'shared' => false,
            ];
        }
    }

    /**
     * The Container's name-normalizer lowercases class identifiers
     * internally — that's the framework reality, and the inspector
     * surfaces it honestly. Agents that pass canonical-cased FQCNs to
     * `inspectOne()` still work because we lookup-normalize there too.
     *
     * Best-effort case recovery: if PHP's already-declared class/
     * interface table contains a matching entry, return its canonical
     * spelling — but never trigger autoload (which is filesystem-case-
     * sensitive under PSR-4 and would fail for the lowercased input).
     */
    private function displayName(string $normalized): string
    {
        $lower = strtolower($normalized);
        foreach (get_declared_classes() as $class) {
            if (strtolower($class) === $lower) {
                return $class;
            }
        }

        foreach (get_declared_interfaces() as $interface) {
            if (strtolower($interface) === $lower) {
                return $interface;
            }
        }

        return $normalized;
    }

    /**
     * @return list<array{ name: string, type: string }>
     */
    private function dependenciesFor(string $className): array
    {
        if (!class_exists($className) && !interface_exists($className)) {
            return [];
        }

        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        if (!$constructor instanceof ReflectionMethod) {
            return [];
        }

        $out = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType
                ? $type->getName()
                : ($type === null ? 'mixed' : (string) $type);
            $out[] = ['name' => $param->getName(), 'type' => $typeName];
        }

        return $out;
    }

    private function describeCallable(mixed $callable): string
    {
        if (\is_string($callable)) {
            return $callable;
        }

        if (\is_array($callable) && \count($callable) === 2 && \is_string($callable[1])) {
            $left = \is_object($callable[0]) ? $callable[0]::class : (string) $callable[0];

            return $left . '::' . $callable[1];
        }

        if ($callable instanceof Closure) {
            return 'Closure';
        }

        return \is_object($callable) ? $callable::class : '(callable)';
    }

    private function describeValue(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_object($value)) {
            return $value::class;
        }

        try {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '(unserialisable)';
        }
    }
}
