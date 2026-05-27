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
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;

final readonly class TestPassedSubscriber implements PassedSubscriber
{
    public function __construct(private ResultCollector $collector) {}

    #[Override]
    public function notify(Passed $event): void
    {
        $this->collector->recordPassed();
    }
}
