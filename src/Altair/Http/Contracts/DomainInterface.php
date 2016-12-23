<?php
namespace Altair\Http\Contracts;

interface DomainInterface
{
    /**
     * Handle domain logic for an action.
     *
     * @param array $input
     *
     * @return PayloadInterface
     */
    public function __invoke(array $input): PayloadInterface;
}
