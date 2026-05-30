<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Library\Exception;

final class ExampleNotFoundException extends ExamplesException
{
    public static function id(string $id): self
    {
        return new self(\sprintf('No example with id "%s" was found in the library.', $id));
    }
}
