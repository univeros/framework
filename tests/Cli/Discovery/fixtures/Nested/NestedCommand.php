<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Discovery\fixtures\Nested;

use Altair\Cli\Attribute\Command;

#[Command(name: 'fixture:nested', description: 'A nested fixture command')]
final class NestedCommand
{
    public function __invoke(): int
    {
        return 0;
    }
}
