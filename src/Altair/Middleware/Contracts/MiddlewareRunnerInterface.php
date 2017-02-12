<?php
namespace Altair\Middleware\Contracts;

interface MiddlewareRunnerInterface
{
    /**
     * Calls the next entry in the queue.
     *
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload): PayloadInterface;
}
