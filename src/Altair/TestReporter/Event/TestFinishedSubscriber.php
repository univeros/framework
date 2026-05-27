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
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

final readonly class TestFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    #[Override]
    public function notify(Finished $event): void
    {
        $this->collector->recordAssertions($event->numberOfAssertionsPerformed());
    }
}
