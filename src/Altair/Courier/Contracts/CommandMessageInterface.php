<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface CommandMessageInterface
{
    /**
     * Returns the message name.
     */
    public function getName(): string;

    /**
     * Returns the LogMessageInterface $message if it has one.
     */
    public function getLogMessage(): ?LogMessageInterface;

    /**
     * Stores a LogMessageInterface on the command message so middleware downstream
     * of the command's execution (notably CommandLoggerMiddleware) can surface it
     * via getLogMessage(). Mutating-by-design: the command bus assumes a single
     * message instance flows through the middleware chain.
     */
    public function setLogMessage(LogMessageInterface $message): void;
}
