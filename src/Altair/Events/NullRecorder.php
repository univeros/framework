<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events;

use Altair\Events\Contracts\RecorderInterface;
use Override;

/**
 * Drops every event on the floor.
 *
 * Bound by EventsConfiguration when the host application opts out of
 * persistent event recording (or when running in a read-only environment
 * like a Docker image build).
 */
final readonly class NullRecorder implements RecorderInterface
{
    #[Override]
    public function record(Event $event): void
    {
        // intentionally empty
    }
}
