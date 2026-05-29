<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

interface InvokerInterface
{
    /**
     * Invoke a callable, a `[class-or-object, method]` pair, a `Class::method`
     * string, or an invokable class name — autowiring the parameters the caller
     * does not supply.
     *
     * @param callable|array{0: object|class-string, 1: string}|string $target
     * @param array<string, mixed> $parameters
     */
    public function call(callable|array|string $target, array $parameters = []): mixed;
}
