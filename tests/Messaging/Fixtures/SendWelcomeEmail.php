<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Fixtures;

final readonly class SendWelcomeEmail
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}
