<?php
namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\MiddlewareInterface;

interface RuleInterface extends MiddlewareInterface
{
    /**
     * Checks whether a value passes rule specs validation.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function assert($value): bool;
}
