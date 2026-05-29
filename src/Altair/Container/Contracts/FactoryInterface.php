<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Contracts;

interface FactoryInterface
{
    /**
     * Construct a fresh instance of $class, autowiring its dependencies and
     * applying the given parameter overrides (by name).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, mixed> $parameters
     *
     * @return T
     */
    public function make(string $class, array $parameters = []): object;
}
