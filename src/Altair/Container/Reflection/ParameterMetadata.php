<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Reflection;

use Altair\Container\Attribute\Autowire;
use Altair\Container\Attribute\Inject;

/**
 * Immutable, cache-safe snapshot of a single constructor/callable parameter.
 * Holds only plain data (no live `Reflection*` objects) so it round-trips
 * through a file cache.
 */
final readonly class ParameterMetadata
{
    /**
     * @param list<string> $types      every declared type name (for messages)
     * @param list<string> $classTypes the subset that are class/interface names, in declared order
     */
    public function __construct(
        public string $name,
        public int $position,
        public array $types,
        public array $classTypes,
        public bool $isIntersection,
        public bool $allowsNull,
        public bool $isVariadic,
        public bool $hasDefault,
        public mixed $default,
        public ?Inject $inject = null,
        public ?Autowire $autowire = null,
    ) {}

    /**
     * The primary class/interface candidate to autowire, if the parameter has one.
     */
    public function primaryClassType(): ?string
    {
        return $this->classTypes[0] ?? null;
    }
}
