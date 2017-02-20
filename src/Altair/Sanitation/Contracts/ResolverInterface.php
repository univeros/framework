<?php
namespace Altair\Sanitation\Contracts;

interface ResolverInterface
{
    /**
     *
     * Converts a middleware queue filter entry to an implementation of
     * FilterInterface.
     *
     * @param mixed $entry The middleware sanitation queue entry.
     *
     * @return FilterInterface
     */
    public function __invoke($entry): FilterInterface;
}
