<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Definition;

use Altair\Container\Contracts\DefinitionInterface;
use Closure;
use Override;

/**
 * Fluent registration handle for one binding. This is a *builder*, not a value
 * object: `Container::bind()` stores it and returns it so the caller can keep
 * configuring (`->to()`, `->shared()`, `->lazy()`, `->tag()`, …). The resolver
 * reads it back through {@see DefinitionInterface}.
 */
final class Definition implements DefinitionInterface
{
    private ?string $concrete = null;

    private ?Closure $factory = null;

    private bool $hasInstance = false;

    private ?object $instance = null;

    private bool $hasValue = false;

    private mixed $value = null;

    private bool $shared = false;

    private bool $lazy = false;

    /**
     * @var list<string>
     */
    private array $tags = [];

    /**
     * @var array<string, mixed>
     */
    private array $parameters = [];

    public function __construct(private readonly string $id) {}

    public function to(string $concrete): self
    {
        $this->concrete = $concrete;

        return $this;
    }

    public function using(Closure $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    public function withInstance(object $instance): self
    {
        $this->instance = $instance;
        $this->hasInstance = true;
        $this->shared = true;

        return $this;
    }

    public function withValue(mixed $value): self
    {
        $this->value = $value;
        $this->hasValue = true;

        return $this;
    }

    public function shared(bool $shared = true): self
    {
        $this->shared = $shared;

        return $this;
    }

    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    public function tag(string ...$tags): self
    {
        foreach ($tags as $tag) {
            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function withParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function concrete(): ?string
    {
        return $this->concrete;
    }

    #[Override]
    public function factory(): ?Closure
    {
        return $this->factory;
    }

    #[Override]
    public function hasInstance(): bool
    {
        return $this->hasInstance;
    }

    #[Override]
    public function instance(): ?object
    {
        return $this->instance;
    }

    #[Override]
    public function hasValue(): bool
    {
        return $this->hasValue;
    }

    #[Override]
    public function value(): mixed
    {
        return $this->value;
    }

    #[Override]
    public function isShared(): bool
    {
        return $this->shared;
    }

    #[Override]
    public function isLazy(): bool
    {
        return $this->lazy;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function parameters(): array
    {
        return $this->parameters;
    }
}
