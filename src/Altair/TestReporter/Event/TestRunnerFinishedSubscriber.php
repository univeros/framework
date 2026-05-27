<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Event;

use Altair\TestReporter\Output\JsonWriter;
use Altair\TestReporter\ResultCollector;
use Override;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Runner\Version;

/**
 * Final emission hook — builds the report and hands it to the writer.
 *
 * Subscribes to {@see ExecutionFinished} (after the last test) rather
 * than the framework-level Finished so we don't fire before subscribers
 * for individual tests have settled.
 */
final readonly class TestRunnerFinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function __construct(
        private ResultCollector $collector,
        private JsonWriter $writer,
    ) {}

    #[Override]
    public function notify(ExecutionFinished $event): void
    {
        $report = $this->collector->build(Version::id());
        $this->writer->emit($report);
    }
}
