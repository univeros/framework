<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Fixtures;

final readonly class PingMessage
{
    public function __construct(public string $note = '') {}
}
