<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown by `get()` when no entry and no instantiable class match the id.
 */
class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('No entry or instantiable class found for "%s".', $id));
    }
}
