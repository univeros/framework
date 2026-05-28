<?php

declare(strict_types=1);

namespace Altair\Tests\Happen\Fixtures;

use Psr\EventDispatcher\StoppableEventInterface;

final class StoppableEvent implements StoppableEventInterface
{
    public function __construct(private bool $stopped = false)
    {
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
