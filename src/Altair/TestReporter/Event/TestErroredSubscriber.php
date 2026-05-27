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
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;

final readonly class TestErroredSubscriber implements ErroredSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    #[Override]
    public function notify(Errored $event): void
    {
        $this->collector->recordError(
            test: $event->test(),
            message: $event->throwable()->message(),
            type: $this->shortClassName($event->throwable()->className()),
        );
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
