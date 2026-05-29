<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container;

use Altair\Container\Contracts\DefinitionInterface;
use Altair\Container\Contracts\FactoryInterface;
use Altair\Container\Contracts\InvokerInterface;
use Altair\Container\Contracts\ReflectorInterface;
use Altair\Container\Definition\ContextualBindingBuilder;
use Altair\Container\Definition\Definition;
use Altair\Container\Exception\ContainerException;
use Altair\Container\Exception\NotFoundException;
use Altair\Container\Lazy\LazyFactory;
use Altair\Container\Reflection\CachedReflector;
use Altair\Container\Resolution\Invoker;
use Altair\Container\Resolution\ParameterResolver;
use Altair\Container\Resolution\ResolutionStack;
use Altair\Container\Resolution\Resolver;
use Altair\Container\Support\NameNormalizer;
use Closure;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionException;

/**
 * A runtime, auto-wiring dependency-injection container.
 *
 * Resolves typed constructor dependencies by reflection (cached), with fluent
 * bindings, attribute autowiring, contextual bindings, tagged services, lazy
 * services, and isolated child scopes. PSR-11 compliant; it resolves its own
 * type to the active instance.
 */
final class Container implements ContainerInterface, FactoryInterface, InvokerInterface
{
    private readonly ReflectorInterface $reflector;

    private readonly ResolutionStack $stack;

    private readonly ParameterResolver $parameterResolver;

    private readonly Resolver $resolver;

    private readonly Invoker $invoker;

    private readonly LazyFactory $lazyFactory;

    /**
     * @var array<string, Definition>
     */
    private array $definitions = [];

    /**
     * @var array<string, object>
     */
    private array $singletons = [];

    /**
     * @var array<string, array<string, Closure>>
     */
    private array $contextual = [];

    /**
     * @var array<string, list<Closure>>
     */
    private array $extenders = [];

    /**
     * @var array<string, true>
     */
    private array $selfNames = [];

    public function __construct(
        ?ReflectorInterface $reflector = null,
        private readonly ?Container $parent = null,
    ) {
        $this->reflector = $reflector ?? new CachedReflector();
        $this->stack = new ResolutionStack();
        $this->parameterResolver = new ParameterResolver($this);
        $this->resolver = new Resolver($this, $this->reflector, $this->parameterResolver);
        $this->invoker = new Invoker($this, $this->reflector, $this->parameterResolver);
        $this->lazyFactory = new LazyFactory();

        foreach ([self::class, FactoryInterface::class, InvokerInterface::class, ContainerInterface::class] as $name) {
            $this->selfNames[NameNormalizer::normalize($name)] = true;
        }
    }

    #[Override]
    public function get(string $id): mixed
    {
        $key = NameNormalizer::normalize($id);

        if (isset($this->selfNames[$key])) {
            return $this;
        }

        if (isset($this->singletons[$key])) {
            return $this->singletons[$key];
        }

        $definition = $this->definitions[$key] ?? null;
        if ($definition !== null) {
            return $this->resolveDefinition($key, $definition);
        }

        if ($this->parent instanceof \Altair\Container\Container && $this->parent->has($id)) {
            return $this->parent->get($id);
        }

        if (class_exists($id)) {
            return $this->applyExtenders($key, $this->build($id, []));
        }

        throw NotFoundException::forId($id);
    }

    #[Override]
    public function has(string $id): bool
    {
        $key = NameNormalizer::normalize($id);

        if (isset($this->selfNames[$key]) || isset($this->singletons[$key]) || isset($this->definitions[$key])) {
            return true;
        }

        return $this->parent?->has($id) ?? false;
    }

    /**
     * @template T of object
     *
     * @param class-string<T>      $class
     * @param array<string, mixed> $parameters
     *
     * @return T
     */
    #[Override]
    public function make(string $class, array $parameters = []): object
    {
        $key = NameNormalizer::normalize($class);
        $definition = $this->definition($key);

        if ($definition instanceof DefinitionInterface && !$definition->hasValue()) {
            $instance = $definition->instance();
            if ($instance !== null) {
                /** @var T $instance */
                return $instance;
            }

            $factory = $definition->factory();
            if ($factory instanceof Closure) {
                /** @var T $made */
                $made = $this->applyExtenders($key, $this->invokeForObject($factory));

                return $made;
            }

            /** @var T $built */
            $built = $this->applyExtenders(
                $key,
                $this->build($definition->concrete() ?? $class, array_merge($definition->parameters(), $parameters))
            );

            return $built;
        }

        /** @var T $object */
        $object = $this->applyExtenders($key, $this->build($class, $parameters));

        return $object;
    }

    /**
     * @param callable|array{0: object|class-string, 1: string}|string $target
     * @param array<string, mixed>                                     $parameters
     */
    #[Override]
    public function call(callable|array|string $target, array $parameters = []): mixed
    {
        try {
            return $this->invoker->call($target, $parameters, $this->stack);
        } catch (ReflectionException $reflectionException) {
            throw new ContainerException('Cannot invoke callable: ' . $reflectionException->getMessage(), 0, $reflectionException);
        }
    }

    public function bind(string $id): Definition
    {
        $definition = new Definition($id);
        $this->definitions[NameNormalizer::normalize($id)] = $definition;

        return $definition;
    }

    public function singleton(string $id, Closure|string|null $concrete = null): Definition
    {
        $definition = $this->bind($id)->shared();

        if ($concrete instanceof Closure) {
            $definition->using($concrete);
        } elseif (\is_string($concrete)) {
            $definition->to($concrete);
        }

        return $definition;
    }

    public function factory(string $id, Closure $factory): Definition
    {
        return $this->bind($id)->using($factory);
    }

    public function instance(string $id, object $instance): Definition
    {
        $this->singletons[NameNormalizer::normalize($id)] = $instance;

        return $this->bind($id)->withInstance($instance);
    }

    public function value(string $id, mixed $value): Definition
    {
        return $this->bind($id)->withValue($value);
    }

    /**
     * @param class-string $concrete
     */
    public function alias(string $abstract, string $concrete): Definition
    {
        return $this->bind($abstract)->to($concrete);
    }

    public function when(string $consumer): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $consumer);
    }

    /**
     * Register a decorator run against $id immediately after it is resolved.
     * Decorators stack (multiple may apply) and run in registration order; a
     * decorator that returns an object replaces the instance, otherwise the
     * original is kept (allowing side-effect-only hooks).
     *
     * @param Closure(object, Container): mixed $decorator
     */
    public function extend(string $id, Closure $decorator): void
    {
        $this->extenders[NameNormalizer::normalize($id)][] = $decorator;
    }

    /**
     * Resolve every service tagged with $tag (lazily, in registration order).
     *
     * @return iterable<mixed>
     */
    public function tagged(string $tag): iterable
    {
        $ids = [];
        foreach ($this->mergedDefinitions() as $key => $definition) {
            if (\in_array($tag, $definition->tags(), true)) {
                $ids[$key] = true;

                continue;
            }

            $concrete = $definition->concrete() ?? $definition->id();
            if (class_exists($concrete) && \in_array($tag, $this->reflector->classMetadata($concrete)->tags, true)) {
                $ids[$key] = true;
            }
        }

        foreach (array_keys($ids) as $key) {
            yield $this->get($key);
        }
    }

    /**
     * Create a child scope: it inherits this container's definitions but keeps
     * its own singleton store and may override bindings without affecting the
     * parent.
     */
    public function createScope(): self
    {
        return new self($this->reflector, $this);
    }

    public function addContextualBinding(string $consumer, string $type, Closure $resolver): void
    {
        $this->contextual[NameNormalizer::normalize($consumer)][NameNormalizer::normalize($type)] = $resolver;
    }

    public function contextualBinding(string $consumer, string $type): ?Closure
    {
        $consumerKey = NameNormalizer::normalize($consumer);
        $typeKey = NameNormalizer::normalize($type);

        return $this->contextual[$consumerKey][$typeKey]
            ?? $this->parent?->contextualBinding($consumer, $type);
    }

    public function parameterValue(string $name): mixed
    {
        $definition = $this->definition(NameNormalizer::normalize($name));

        if ($definition instanceof DefinitionInterface && $definition->hasValue()) {
            return $definition->value();
        }

        if ($definition instanceof DefinitionInterface && $definition->hasInstance()) {
            return $definition->instance();
        }

        throw new ContainerException(\sprintf('No container parameter "%s" is registered.', $name));
    }

    /**
     * @return array<string, DefinitionInterface>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * The instances the container has actually realised and is sharing.
     *
     * @return array<string, object>
     */
    public function getRealisedSingletons(): array
    {
        return $this->singletons;
    }

    private function resolveDefinition(string $key, DefinitionInterface $definition): mixed
    {
        if ($definition->hasInstance()) {
            return $definition->instance();
        }

        if ($definition->hasValue()) {
            return $definition->value();
        }

        $object = $this->produce($definition);

        if (\is_object($object)) {
            $object = $this->applyExtenders($key, $object);
        }

        if ($definition->isShared() && \is_object($object)) {
            $this->singletons[$key] = $object;
        }

        return $object;
    }

    private function applyExtenders(string $key, object $object): object
    {
        foreach ($this->extendersFor($key) as $decorator) {
            $result = $decorator($object, $this);
            if (\is_object($result)) {
                $object = $result;
            }
        }

        return $object;
    }

    /**
     * @return list<Closure>
     */
    private function extendersFor(string $key): array
    {
        return array_merge($this->parent?->extendersFor($key) ?? [], $this->extenders[$key] ?? []);
    }

    private function produce(DefinitionInterface $definition): mixed
    {
        if ($definition->isLazy()) {
            $concrete = $definition->concrete() ?? $definition->id();
            $class = class_exists($concrete) ? $concrete : null;

            return $this->lazyFactory->create($class, fn(): mixed => $this->produceEager($definition));
        }

        return $this->produceEager($definition);
    }

    private function produceEager(DefinitionInterface $definition): mixed
    {
        $factory = $definition->factory();
        if ($factory instanceof Closure) {
            return $this->call($factory);
        }

        $concrete = $definition->concrete();

        // An alias (`->to(B)`) pointing at a separately-bound concrete must
        // resolve B through its own definition (factory, params, shared, …),
        // not build it blindly. Only when the alias adds no parameters of its own.
        if ($concrete !== null && $definition->parameters() === []) {
            $concreteKey = NameNormalizer::normalize($concrete);
            if ($concreteKey !== NameNormalizer::normalize($definition->id()) && $this->definition($concreteKey) instanceof DefinitionInterface) {
                return $this->get($concrete);
            }
        }

        return $this->build($concrete ?? $definition->id(), $definition->parameters());
    }

    private function invokeForObject(Closure $factory): object
    {
        $object = $this->call($factory);

        if (!\is_object($object)) {
            throw new ContainerException('Factory closure must return an object.');
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $callTime
     */
    private function build(string $class, array $callTime): object
    {
        $key = NameNormalizer::normalize($class);
        $this->stack->enter($key);

        try {
            return $this->resolver->instantiate($class, $callTime, $this->stack);
        } finally {
            $this->stack->leave($key);
        }
    }

    private function definition(string $key): ?DefinitionInterface
    {
        return $this->definitions[$key] ?? $this->parent?->definition($key);
    }

    /**
     * @return array<string, Definition>
     */
    private function mergedDefinitions(): array
    {
        $parent = $this->parent?->mergedDefinitions() ?? [];

        return array_merge($parent, $this->definitions);
    }
}
