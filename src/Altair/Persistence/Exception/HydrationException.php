<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Exception;

use RuntimeException;

final class HydrationException extends RuntimeException implements PersistenceExceptionInterface
{
    public static function uncoercible(string $dataObjectClass, string $field, string $expected, mixed $value): self
    {
        return new self(\sprintf(
            'Cannot hydrate %s::$%s: value of type %s is not coercible to %s.',
            $dataObjectClass,
            $field,
            get_debug_type($value),
            $expected,
        ));
    }
}
