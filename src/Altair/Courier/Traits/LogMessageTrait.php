<?php

namespace Altair\Courier\Traits;

use Altair\Courier\Contracts\CommandMessageInterface;
use Altair\Courier\Contracts\LogMessageInterface;

trait LogMessageTrait
{
    protected $logMessage;

    /**
     * Returns the LogMessageInterface $message if it has one.
     *
     * @return LogMessageInterface|null
     */
    public function getLogMessage(): ?LogMessageInterface
    {
        return $this->logMessage;
    }

    /**
     * Returns the instance with a LogMessageInterface $message so user can extract its log information
     * to use it on its logger system.
     *
     * @param LogMessageInterface $message
     *
     * @return CommandMessageInterface
     */
    public function withLogMessage(LogMessageInterface $message): CommandMessageInterface
    {
        $this->logMessage = $message;
        
        return $this;
    }
}
