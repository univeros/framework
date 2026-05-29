<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

use Closure;

/**
 * Read surface of a registered binding, consumed by the resolver and by
 * introspection. The fluent registration methods live on the concrete
 * {@see \Altair\Container\Definition\Definition}.
 */
interface DefinitionInterface
{
    public function id(): string;

    /**
     * Target class name when the binding redirects to a concrete type.
     */
    public function concrete(): ?string;

    public function factory(): ?Closure;

    public function hasInstance(): bool;

    public function instance(): ?object;

    public function hasValue(): bool;

    public function value(): mixed;

    public function isShared(): bool;

    public function isLazy(): bool;

    /**
     * @return list<string>
     */
    public function tags(): array;

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array;
}
