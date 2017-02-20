<?php
namespace Altair\Sanitation\Contracts;

use Altair\Middleware\Contracts\MiddlewareInterface;

interface FilterInterface extends MiddlewareInterface
{
    /**
     * Parses the value and sanitizes it.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function parse($value);
}
