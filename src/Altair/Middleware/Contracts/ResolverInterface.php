<?php
namespace Altair\Middleware\Contracts;

interface ResolverInterface
{
    /**
     *
     * Converts a middleware queue entry to an implementation of
     * MiddlewareInterface.
     *
     * @param mixed $entry The middleware queue entry.
     *
     * @return MiddlewareInterface
     */
    public function __invoke($entry): MiddlewareInterface;
}
