<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Middleware;

use Altair\Courier\Contracts\LogMessageInterface;
use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CommandLoggerMiddleware implements CommandMiddlewareInterface
{

    /**
     * LoggerMiddleware constructor.
     */
    public function __construct(protected LoggerInterface $logger, protected string $level = LogLevel::INFO)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function handle(CommandMessageInterface $message, callable $next): void
    {
        $level = $this->level;

        $this->logger->log($level, 'Started handling a message', ['message' => $message]);

        $next($message);

        $log = $message->getLogMessage();

        if ($log instanceof LogMessageInterface && $log->getLevel() !== $this->level) {
            $level = $log->getLevel();
        }

        $this->logger->log($level, 'Finished handling a message', ['message' => $message, 'log' => $log]);
    }
}
