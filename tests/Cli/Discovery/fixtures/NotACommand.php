<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Discovery\fixtures;

/**
 * Plain class without #[Command]; should be ignored by the discoverer
 * even though it lives under a scanned path.
 */
final class NotACommand
{
    public function someMethod(): string
    {
        return 'not a command';
    }
}
