<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Traits;

use Altair\Courier\Contracts\LogMessageInterface;

trait LogMessageTrait
{
    protected $logMessage;

    /**
     * Returns the LogMessageInterface $message if it has one.
     */
    public function getLogMessage(): ?LogMessageInterface
    {
        return $this->logMessage;
    }

    /**
     * Stores a LogMessageInterface $message on the command message so downstream
     * middleware (notably CommandLoggerMiddleware) can surface it via getLogMessage().
     *
     * Named setLogMessage, not withLogMessage, because the command-bus dispatch is
     * mutation-based: CommandInterface::exec() returns void, and middleware reads
     * the log message from the same instance after $next() returns.
     */
    public function setLogMessage(LogMessageInterface $message): void
    {
        $this->logMessage = $message;
    }
}
