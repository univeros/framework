<?php
namespace Altair\Cache\Traits;

trait FailureReasonAwareTrait
{
    protected $reason;

    /**
     * Returns the error message if validation has failed, if it has failed.
     *
     * @return string|null
     */
    public function getFailureReason(): ?string
    {
        return $this->reason;
    }
}
