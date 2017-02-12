<?php
namespace Altair\Middleware\Contracts;

interface MiddlewareManagerInterface
{
    /**
     * Fires the runner to process all middleware.
     *
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload);
}
