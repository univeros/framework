<?php

declare(strict_types=1);

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
     */
    public function getLevel(): string;

    /**
     * Returns the log message.
     */
    public function getMessage(): string;
}
