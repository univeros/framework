<?php
namespace Altair\Cache\Contracts;

interface FailureReasonAwareInterface
{
    /**
     * Returns the error message if validation has failed, if it has failed.
     *
     * @return string|null
     */
    public function getFailureReason(): ?string;
}
