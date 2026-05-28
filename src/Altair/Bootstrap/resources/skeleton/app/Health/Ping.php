<?php

declare(strict_types=1);

namespace App\Health;

use Altair\Http\Contracts\PayloadInterface;
use App\Http\Inputs\PingInput;

/**
 * The proof-of-life domain: returns a small health payload. This is the one
 * endpoint the skeleton ships fully implemented — every endpoint you scaffold
 * afterwards follows the same Input → Domain → Responder shape.
 */
final class Ping
{
    public function __invoke(PingInput $input, PayloadInterface $payload): PayloadInterface
    {
        return $payload
            ->withStatus(200)
            ->withOutput(['message' => 'ok', 'timestamp' => gmdate('c')]);
    }
}
