<?php
namespace Altair\Courier\Support;

use Altair\Courier\Contracts\LogMessageInterface;
use Psr\Log\LogLevel;

class LogMessage implements LogMessageInterface
{
    /**
     * @var string
     */
    protected $message;
    /**
     * @var string
     */
    protected $level;

    /**
     * LogMessage constructor.
     *
     * @param string $message
     * @param string $level
     */
    public function __construct(string $message, string $level = LogLevel::INFO)
    {
        $this->message = $message;
        $this->level = $level;
    }

    /**
     * @inheritdoc
     */
    public function __toString()
    {
        return $this->getMessage();
    }

    /**
     * @inheritdoc
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns cloned version with new level.
     *
     * @param string $level
     *
     * @return LogMessageInterface
     */
    public function withLevel(string $level): LogMessageInterface
    {
        $cloned = clone $this;
        $cloned->level = $level;

        return $cloned;
    }

    /**
     * Returns cloned version with new message.
     *
     * @param string $message
     *
     * @return LogMessageInterface
     */
    public function withMessage(string $message): LogMessageInterface
    {
        $cloned = clone $this;
        $cloned->message = $message;

        return $cloned;
    }
}
