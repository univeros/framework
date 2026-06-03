<?php

declare(strict_types=1);

namespace VendorModule\Domain;

use Altair\Http\Contracts\PayloadInterface;
use VendorModule\Http\Inputs\SampleInput;

/**
 * The domain behind GET /sample. Replace this with your module's real logic;
 * every endpoint you add follows the same Input -> Domain -> Responder shape.
 */
final class SampleService
{
    public function __invoke(SampleInput $input, PayloadInterface $payload): PayloadInterface
    {
        return $payload
            ->withStatus(200)
            ->withOutput(['module' => 'vendor/module', 'message' => 'hello from your module']);
    }
}
