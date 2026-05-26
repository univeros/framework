<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Support;

use Altair\Courier\Contracts\LogMessageInterface;
use Override;
use Psr\Log\LogLevel;

class LogMessage implements LogMessageInterface
{
    /**
     * LogMessage constructor.
     */
    public function __construct(protected string $message, protected string $level = LogLevel::INFO) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns cloned version with new level.
     *
     *
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
     *
     */
    public function withMessage(string $message): LogMessageInterface
    {
        $cloned = clone $this;
        $cloned->message = $message;

        return $cloned;
    }
}
