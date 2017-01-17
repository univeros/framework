<?php
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
