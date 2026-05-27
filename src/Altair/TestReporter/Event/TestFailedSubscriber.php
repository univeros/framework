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
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;

final readonly class TestFailedSubscriber implements FailedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    #[Override]
    public function notify(Failed $event): void
    {
        $comparison = $event->hasComparisonFailure() ? $event->comparisonFailure() : null;
        $this->collector->recordFailure(
            test: $event->test(),
            message: $event->throwable()->message(),
            comparison: $comparison,
            type: $this->shortClassName($event->throwable()->className()),
        );
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
