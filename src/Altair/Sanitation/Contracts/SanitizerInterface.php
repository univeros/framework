<?php
namespace Altair\Sanitation\Contracts;

use Altair\Middleware\Contracts\PayloadInterface;

interface SanitizerInterface
{
    /**
     * Validates an input against the collection of rules the validator has.
     *
     * @param SanitizableInterface $sanitizable
     *
     * @return SanitizableInterface
     */
    public function sanitize(SanitizableInterface $sanitizable): SanitizableInterface;

    /**
     * Returns the payload that went throughout the queue of validation rules.
     *
     * @return PayloadInterface|null
     */
    public function getPayload(): ?PayloadInterface;
}
