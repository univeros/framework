<?php
namespace Altair\Courier\Contracts;

interface MiddlewareResolverInterface
{
    /**
     *
     * Converts a middleware queue entry to a callable or an implementation of
     * MiddlewareInterface.
     *
     * @param mixed $entry The middleware queue entry.
     *
     * @return callable|CommandMiddlewareInterface
     *
     */
    public function __invoke($entry);
}
