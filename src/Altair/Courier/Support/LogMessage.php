<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->getMessage();
    }

    /**
     * @inheritDoc
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @inheritDoc
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
