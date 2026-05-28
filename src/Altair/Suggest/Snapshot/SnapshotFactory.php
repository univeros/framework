<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Snapshot;

use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\PipelineInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Introspection\Inspector\SpecInspector;
use ReflectionClass;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

/**
 * Builds a {@see Snapshot} from the introspection inspectors, enriching the
 * raw container view with reflection-derived dependency and interface data.
 *
 * Every inspector is optional: a host that does not use FastRoute, Happen,
 * Relay, or the spec scaffolder simply leaves those constructor arguments
 * null, and the corresponding snapshot section comes back empty. This is the
 * one place in the package that touches reflection — the inspectors stay
 * instantiation-safe (they only read already-built definitions), and so does
 * this factory: it reflects classes, it never constructs them.
 */
final readonly class SnapshotFactory
{
    public function __construct(
        private ?ContainerInspector $container = null,
        private ?RouteInspector $routes = null,
        private ?ListenerInspector $listeners = null,
        private ?PipelineInspector $pipeline = null,
        private ?SpecInspector $specs = null,
    ) {}

    public function create(): Snapshot
    {
        return new Snapshot(
            bindings: $this->bindings(),
            routes: $this->routeNodes(),
            events: $this->eventNodes(),
            middleware: $this->middleware(),
            specs: $this->specNodes(),
        );
    }

    /**
     * @return list<BindingNode>
     */
    private function bindings(): array
    {
        if (!$this->container instanceof ContainerInspector) {
            return [];
        }

        $nodes = [];
        foreach ($this->container->inspectAll()->rows as $row) {
            $id = $this->str($row, 'id');
            $kind = $this->str($row, 'kind');
            $target = $this->str($row, 'target');

            if ($kind === 'parameter') {
                continue;
            }

            $nodes[] = new BindingNode(
                id: $id,
                kind: $kind,
                target: $target,
                shared: (bool) ($row['shared'] ?? false),
                dependencies: $this->dependenciesOf($target),
                interfaces: $this->interfacesOf($target),
            );
        }

        return $nodes;
    }

    /**
     * Constructor object-parameter types for a class target. Scalar, array,
     * and builtin parameters are dropped — only collaborator types are edges
     * in the dependency graph.
     *
     * @return list<string>
     */
    private function dependenciesOf(string $target): array
    {
        if (!class_exists($target)) {
            return [];
        }

        $constructor = (new ReflectionClass($target))->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $types = [];
        foreach ($constructor->getParameters() as $parameter) {
            foreach ($this->namedTypes($parameter->getType()) as $name) {
                $types[] = $name;
            }
        }

        return $types;
    }

    /**
     * The non-builtin class/interface names a parameter type refers to. A
     * simple type yields one; a union or intersection yields each member, so
     * a `Foo|Bar` collaborator counts as two dependency edges rather than
     * being silently dropped.
     *
     * @return list<string>
     */
    private function namedTypes(?ReflectionType $type): array
    {
        if ($type instanceof ReflectionNamedType) {
            return $type->isBuiltin() ? [] : [$type->getName()];
        }

        if (!$type instanceof ReflectionUnionType && !$type instanceof ReflectionIntersectionType) {
            return [];
        }

        $names = [];
        foreach ($type->getTypes() as $member) {
            if ($member instanceof ReflectionNamedType && !$member->isBuiltin()) {
                $names[] = $member->getName();
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function interfacesOf(string $target): array
    {
        if (!class_exists($target) && !interface_exists($target)) {
            return [];
        }

        $interfaces = class_implements($target);

        return $interfaces === false ? [] : array_values($interfaces);
    }

    /**
     * @return list<RouteNode>
     */
    private function routeNodes(): array
    {
        if (!$this->routes instanceof RouteInspector) {
            return [];
        }

        $nodes = [];
        foreach ($this->routes->inspectAll()->rows as $row) {
            $nodes[] = new RouteNode(
                method: $this->str($row, 'method'),
                path: $this->str($row, 'path'),
                action: $this->str($row, 'action'),
            );
        }

        return $nodes;
    }

    /**
     * @return list<EventNode>
     */
    private function eventNodes(): array
    {
        if (!$this->listeners instanceof ListenerInspector) {
            return [];
        }

        $nodes = [];
        foreach ($this->listeners->inspectAll()->rows as $row) {
            $event = $this->str($row, 'event');
            $count = (int) ($row['listeners'] ?? 0);
            $nodes[] = new EventNode($event, $count, $this->listenerTargets($event, $count));
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function listenerTargets(string $event, int $count): array
    {
        if ($count < 1 || !$this->listeners instanceof ListenerInspector) {
            return [];
        }

        try {
            $rows = $this->listeners->inspectOne($event)->rows;
        } catch (IntrospectionException) {
            return [];
        }

        $targets = [];
        foreach ($rows as $row) {
            $listener = $this->str($row, 'listener');
            $class = strstr($listener, '::', true);
            $class = $class === false ? $listener : $class;
            if (!\in_array($class, ['', 'Closure', '(callable)'], true)) {
                $targets[] = $class;
            }
        }

        return $targets;
    }

    /**
     * @return list<string>
     */
    private function middleware(): array
    {
        if (!$this->pipeline instanceof PipelineInspector) {
            return [];
        }

        $names = [];
        foreach ($this->pipeline->inspectAll()->rows as $row) {
            $name = $this->str($row, 'middleware');
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return list<SpecNode>
     */
    private function specNodes(): array
    {
        if (!$this->specs instanceof SpecInspector) {
            return [];
        }

        $nodes = [];
        foreach ($this->specs->inspectAll()->rows as $row) {
            $nodes[] = new SpecNode(
                path: $this->str($row, 'path'),
                method: $this->str($row, 'method'),
                route: $this->str($row, 'route'),
            );
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? '';

        return \is_scalar($value) ? (string) $value : '';
    }
}
