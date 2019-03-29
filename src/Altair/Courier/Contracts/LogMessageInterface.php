<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface LogMessageInterface
{
    /**
     * @return string
     */
    public function __toString();
    /**
     * Returns the log level.
     *
     * @return string
     */
    public function getLevel(): string;

    /**
     * Returns the log message.
     *
     * @return string
     */
    public function getMessage(): string;
}
