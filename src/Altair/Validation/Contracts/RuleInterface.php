<?php
namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;

interface RuleInterface extends MiddlewareInterface
{
    /**
     * Middleware capable invokable class method.
     *
     * @param PayloadInterface $payload
     * @param callable $next
     *
     * @return mixed
     */
    public function __invoke(PayloadInterface $payload, callable $next);

    /**
     * Checks whether a value passes rule specs validation.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function assert($value): bool;
}
