<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface CommandBusInterface
{
    /**
     * Executes a command message by applying the strategy that it was instantiated withs.
     *
     * @param CommandMessageInterface $message
     *
     * @return mixed
     */
    public function handle(CommandMessageInterface $message);
}
