<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Definition;

use Altair\Container\Container;
use Closure;

/**
 * Fluent builder for a contextual binding: "when {consumer} needs {type}, give
 * {…}". Produced by {@see Container::when()}; terminal `give*()` calls register
 * the binding and hand the container back for chaining.
 */
final class ContextualBindingBuilder
{
    private string $type = '';

    public function __construct(
        private readonly Container $container,
        private readonly string $consumer,
    ) {}

    public function needs(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Resolve the dependency from another service id, or from a factory closure.
     *
     * @param class-string|Closure $concrete
     */
    public function give(string|Closure $concrete): Container
    {
        $resolver = $concrete instanceof Closure
            ? $concrete
            : static fn(Container $container): mixed => $container->get($concrete);

        $this->container->addContextualBinding($this->consumer, $this->type, $resolver);

        return $this->container;
    }

    /**
     * Inject a fixed value for the dependency.
     */
    public function giveValue(mixed $value): Container
    {
        $this->container->addContextualBinding($this->consumer, $this->type, static fn(): mixed => $value);

        return $this->container;
    }
}
