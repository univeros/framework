<?php
namespace Altair\Queue\Contracts;

use Altair\Middleware\Contracts\PayloadInterface;

interface JobProcessorInterface
{
    public function process(PayloadInterface $payload);
}
