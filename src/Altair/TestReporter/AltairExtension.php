<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter;

use Altair\TestReporter\Diff\ValueDiffer;
use Altair\TestReporter\Event\TestConsideredRiskySubscriber;
use Altair\TestReporter\Event\TestErroredSubscriber;
use Altair\TestReporter\Event\TestFailedSubscriber;
use Altair\TestReporter\Event\TestFinishedSubscriber;
use Altair\TestReporter\Event\TestMarkedIncompleteSubscriber;
use Altair\TestReporter\Event\TestPassedSubscriber;
use Altair\TestReporter\Event\TestPreparedSubscriber;
use Altair\TestReporter\Event\TestRunnerFinishedSubscriber;
use Altair\TestReporter\Event\TestSkippedSubscriber;
use Altair\TestReporter\Output\JsonWriter;
use Altair\TestReporter\Resolver\SourceUnderTestResolver;
use Override;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit Extension entrypoint — registers every subscriber so the
 * JSON report gets built as tests run.
 *
 * ## Registration in `phpunit.xml.dist`
 *
 * ```xml
 * <extensions>
 *     <bootstrap class="Altair\TestReporter\AltairExtension">
 *         <parameter name="output" value="json"/>
 *         <parameter name="file" value="build/test-results.json"/>
 *     </bootstrap>
 * </extensions>
 * ```
 *
 * `output=json` (default) emits the structured report at the end of
 * the run. `output=none` disables emission entirely (the extension
 * still runs, but the writer is a no-op — useful when you want only
 * PHPUnit's default human output).
 *
 * `file=<path>` writes the JSON to a file; omit it to write to stdout.
 */
final readonly class AltairExtension implements Extension
{
    #[Override]
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $output = $parameters->has('output') ? $parameters->get('output') : 'json';
        if ($output === 'none') {
            return;
        }

        $file = $parameters->has('file') ? $parameters->get('file') : null;
        $projectRoot = $this->resolveProjectRoot();

        $collector = new ResultCollector(
            resolver: new SourceUnderTestResolver($projectRoot),
            differ: new ValueDiffer(),
        );

        $facade->registerSubscribers(
            new TestPreparedSubscriber($collector),
            new TestPassedSubscriber($collector),
            new TestFailedSubscriber($collector),
            new TestErroredSubscriber($collector),
            new TestSkippedSubscriber($collector),
            new TestMarkedIncompleteSubscriber($collector),
            new TestConsideredRiskySubscriber($collector),
            new TestFinishedSubscriber($collector),
            new TestRunnerFinishedSubscriber($collector, new JsonWriter($file)),
        );
    }

    /**
     * Project root used for relativising emitted file paths in the
     * report. PHPUnit doesn't expose its working directory directly,
     * so we use `getcwd()` — for the framework itself that's the
     * repository root.
     */
    private function resolveProjectRoot(): string
    {
        $cwd = getcwd();

        return $cwd === false ? '.' : $cwd;
    }
}
