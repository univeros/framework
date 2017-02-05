<?php
namespace Altair\Validation\Contracts;

interface ResolverInterface
{
    /**
     *
     * Converts a middleware queue rule entry to an implementation of
     * RuleInterface.
     *
     * @param mixed $entry The middleware rule queue entry.
     *
     * @return RuleInterface
     */
    public function __invoke($entry): RuleInterface;
}
