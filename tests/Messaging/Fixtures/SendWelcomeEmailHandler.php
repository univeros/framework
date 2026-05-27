<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Fixtures;

use Altair\Messaging\Attribute\AsHandler;

#[AsHandler(SendWelcomeEmail::class)]
final class SendWelcomeEmailHandler
{
    /** @var list<SendWelcomeEmail> */
    public array $received = [];

    public function __invoke(SendWelcomeEmail $message): void
    {
        $this->received[] = $message;
    }
}
