<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface CommandRunnerStrategyInterface
{
    /**
     * Runs a strategy to run for command message processing.
     *
     * @param CommandMessageInterface $message
     *
     * @return void
     */
    public function run(CommandMessageInterface $message);
}
