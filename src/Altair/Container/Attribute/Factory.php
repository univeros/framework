<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Attribute;

use Attribute;

/**
 * Build the decorated class through a factory rather than by autowiring its
 * constructor. The factory class is itself resolved from the container and
 * invoked via `$method` (default `__invoke`).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Factory
{
    /**
     * @param class-string $factory
     */
    public function __construct(
        public string $factory,
        public string $method = '__invoke',
    ) {}
}
