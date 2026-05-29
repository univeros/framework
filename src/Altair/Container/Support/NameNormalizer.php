<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Support;

/**
 * Canonicalises container ids so `Foo`, `\Foo` and `foo` map to one key.
 */
final class NameNormalizer
{
    public static function normalize(string $name): string
    {
        return strtolower(ltrim($name, '\\'));
    }
}
