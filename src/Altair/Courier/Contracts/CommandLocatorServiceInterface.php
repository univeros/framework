<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

use Altair\Courier\Exception\UnknownCommandMessageNameException;

interface CommandLocatorServiceInterface
{
    /**
     * Checks whether a CommandInterface exists for that particular message name.
     *
     *
     */
    public function has(string $name): bool;

    /**
     * Returns a CommandInterface for that particular message instance.
     *
     *
     * @throws UnknownCommandMessageNameException if not command has been found
     *
     */
    public function get(string $name): CommandInterface;
}
