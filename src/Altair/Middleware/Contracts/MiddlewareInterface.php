<?php
namespace Altair\Middleware\Contracts;

interface MiddlewareInterface
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
}
