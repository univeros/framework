<?php
namespace Altair\Http\Contracts;

interface IdentityValidatorInterface
{
    /**
     * @param array $arguments
     *
     * @return bool
     */
    public function __invoke(array $arguments);
}
