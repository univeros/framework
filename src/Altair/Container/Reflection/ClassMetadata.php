<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Attribute\Factory;

/**
 * Immutable, cache-safe snapshot of a class: its constructor parameters,
 * whether it can be instantiated, and its class-level container attributes.
 */
final readonly class ClassMetadata
{
    /**
     * @param list<ParameterMetadata> $parameters
     * @param list<string>            $tags
     */
    public function __construct(
        public string $name,
        public bool $isInstantiable,
        public bool $hasConstructor,
        public array $parameters,
        public ?Factory $factory,
        public bool $isLazy,
        public array $tags,
    ) {}
}
