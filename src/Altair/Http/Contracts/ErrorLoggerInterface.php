<?php
namespace Altair\Http\Contracts;

use Psr\Log\LoggerInterface;

interface ErrorLoggerInterface
{
    /**
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;

    /**
     * Log an error or exception
     *
     * @param \Exception|\Error $error
     */
    public function log($error);
}
