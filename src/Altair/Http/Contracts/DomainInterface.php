<?php
namespace Altair\Http\Contracts;

use Altair\Http\Collection\InputCollection;

interface DomainInterface
{
    /**
     * Handle domain logic for an action.
     *
     * @param InputCollection $input
     *
     * @return PayloadInterface
     */
    public function __invoke(InputCollection $input): PayloadInterface;
}
