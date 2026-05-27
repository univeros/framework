<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Event;

use Altair\TestReporter\ResultCollector;
use Override;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;

final readonly class TestSkippedSubscriber implements SkippedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    #[Override]
    public function notify(Skipped $event): void
    {
        $this->collector->recordSkipped(test: $event->test(), reason: $event->message());
    }
}
