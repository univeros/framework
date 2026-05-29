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
use Altair\Container\Contracts\DefinitionInterface;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Result\InspectionTable;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Walks the Container's binding definitions without triggering
 * instantiation — so it's safe to run against a project whose database
 * is down or whose Configuration `extend` hooks would have side effects.
 *
 * `inspectAll()` / `inspectOne()` report definitions (what *would* be
 * built). `inspectRealized()` reports the complementary view — instances
 * the Container has actually constructed so far — for long-running-process
 * introspection (worker memory growth, surprised-by-singleton, extend-hook
 * ordering). It still never instantiates: it only reads the already-built
 * objects sitting in the realised-singletons collection.
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
     * The container's realised-singletons collection holds an entry only
     * once a shared binding (or a pre-made `instance()`) has been built, so
     * every value is a constructed object — the "has been instantiated"
     * signal.
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

        foreach ($this->container->getRealisedSingletons() as $name => $instance) {
            $id = $this->displayName($name);

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
        $definitions = $this->container->getDefinitions();

        // Container collections are keyed by lower-cased class names — match the same normalization.
        $lookupKey = strtolower(ltrim($id, '\\'));
        $definition = $definitions[$lookupKey] ?? null;

        if ($definition instanceof DefinitionInterface) {
            $kind = $this->kindOf($definition);
            $resolved = $this->targetOf($definition);
            $shared = $definition->isShared();
            $lazy = $definition->isLazy();
            $tags = $definition->tags();
        } elseif (class_exists(ltrim($id, '\\')) || interface_exists(ltrim($id, '\\'))) {
            $kind = 'class';
            $resolved = ltrim($id, '\\');
            $shared = false;
            $lazy = false;
            $tags = [];
        } else {
            throw new NotFoundException(\sprintf("No binding for '%s'.", $id));
        }

        $rows = [
            ['field' => 'id', 'value' => $id],
            ['field' => 'kind', 'value' => $kind],
            ['field' => 'target', 'value' => $resolved],
            ['field' => 'shared', 'value' => $shared ? 'true' : 'false'],
        ];

        if ($lazy) {
            $rows[] = ['field' => 'lazy', 'value' => 'true'];
        }

        if ($tags !== []) {
            $rows[] = ['field' => 'tags', 'value' => implode(', ', $tags)];
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
        foreach ($this->container->getDefinitions() as $normalized => $definition) {
            $kind = $this->kindOf($definition);

            if ($kind === 'value') {
                yield [
                    'id' => '$' . $this->displayName($normalized),
                    'kind' => 'parameter',
                    'target' => $this->describeValue($definition->value()),
                    'shared' => false,
                ];

                continue;
            }

            yield [
                'id' => $this->displayName($normalized),
                'kind' => $kind,
                'target' => $this->targetOf($definition),
                'shared' => $definition->isShared(),
            ];
        }
    }

    /**
     * Classify a binding from its read surface. The container expresses
     * aliases as a `to()` redirect, delegates/factories as a closure,
     * pre-made objects via `hasInstance()`, and raw values via `hasValue()`.
     */
    private function kindOf(DefinitionInterface $definition): string
    {
        return match (true) {
            $definition->hasValue() => 'value',
            $definition->hasInstance() => 'share',
            $definition->factory() instanceof Closure => 'delegate',
            $definition->concrete() !== null => 'alias',
            default => 'class',
        };
    }

    private function targetOf(DefinitionInterface $definition): string
    {
        $concrete = $definition->concrete();
        if ($concrete !== null) {
            return $concrete;
        }

        return $this->displayName($definition->id());
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
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '(unserialisable)';
        }
    }
}
