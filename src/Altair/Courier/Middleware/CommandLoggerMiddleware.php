<?php
namespace Altair\Courier\Middleware;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\CommandMiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class CommandLoggerMiddleware implements CommandMiddlewareInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var string
     */
    protected $level;

    /**
     * LoggerMiddleware constructor.
     *
     * @param LoggerInterface $logger
     * @param string $level
     */
    public function __construct(LoggerInterface $logger, string $level = LogLevel::INFO)
    {
        $this->logger = $logger;
        $this->level = $level;
    }

    /**
     * @inheritdoc
     */
    public function handle(CommandMessageInterface $message, callable $next)
    {
        $level = $this->level;

        $this->logger->log($level, 'Started handling a message', ['message' => $message]);

        $next($message);

        $log = $message->getLogMessage();

        if ($log !== null && $log->getLevel() !== $this->level) {
            $level = $log->getLevel();
        }

        $this->logger->log($level, 'Finished handling a message', ['message' => $message, 'log' => $log]);
    }
}
