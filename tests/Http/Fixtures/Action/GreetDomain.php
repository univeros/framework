<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Contracts\PayloadInterface;

/**
 * A spec-scaffold-shaped domain: invokable with the typed DTO + a fresh Payload.
 */
final class GreetDomain
{
    public function __invoke(GreetInput $input, PayloadInterface $payload): PayloadInterface
    {
        return $payload
            ->withStatus(200)
            ->withOutput(['hello' => $input->name, 'times' => $input->times]);
    }
}
