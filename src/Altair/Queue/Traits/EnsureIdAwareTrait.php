<?php
namespace Altair\Queue\Traits;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Security\Support\Salt;

trait EnsureIdAwareTrait
{
    /**
     * Ensures the existence of the id attribute on the payload. If none found, will create one.
     *
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    protected function ensureId(PayloadInterface $payload): PayloadInterface
    {
        return !$this->hasIdAttribute($payload)
            ? $payload->withAttribute(JobInterface::ATTRIBUTE_ID, (new Salt())->generate(32))
            : $payload;
    }
}
