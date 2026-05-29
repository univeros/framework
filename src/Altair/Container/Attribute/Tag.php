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
 * Register the decorated class under one or more tags so it can be resolved as
 * part of a collection via `Container::tagged()`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Tag
{
    /**
     * @var list<string>
     */
    public array $names;

    public function __construct(string ...$names)
    {
        $this->names = array_values($names);
    }
}
