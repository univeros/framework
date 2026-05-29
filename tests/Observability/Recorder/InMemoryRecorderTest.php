<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observability\Recorder;

use Altair\Observability\Contracts\ExporterInterface;
use Altair\Observability\Metrics\MetricKind;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Recorder\InMemoryRecorder;
use Altair\Observability\Trace\Span;
use Altair\Observability\Trace\SpanContext;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\SpanStatus;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRecorder::class)]
final class InMemoryRecorderTest extends TestCase
{
    public function testFlushExportsAndDrainsBuffers(): void
    {
        $exporter = $this->fakeExporter();
        $recorder = new InMemoryRecorder($exporter);

        $recorder->recordSpan($this->makeSpan('a'));
        $recorder->recordMetric($this->makePoint('m'));
        $recorder->flush();

        self::assertCount(1, $exporter->lastSpans);
        self::assertCount(1, $exporter->lastMetrics);
        self::assertSame([], $recorder->spans());
        self::assertSame([], $recorder->metrics());
    }

    public function testAutoFlushHappensAtTheSpanBufferCap(): void
    {
        $exporter = $this->fakeExporter();
        $recorder = new InMemoryRecorder($exporter, maxBufferedSpans: 3, maxBufferedPoints: 1000);

        for ($i = 0; $i < 3; ++$i) {
            $recorder->recordSpan($this->makeSpan('s' . $i));
        }

        self::assertCount(3, $exporter->lastSpans);
        self::assertSame([], $recorder->spans(), 'cap-hit auto-flushes the buffer');
    }

    public function testWithoutAnExporterTheBufferRollsTheOldestOffAtTheCap(): void
    {
        $recorder = new InMemoryRecorder(exporter: null, maxBufferedSpans: 2, maxBufferedPoints: 1000);

        $recorder->recordSpan($this->makeSpan('old'));
        $recorder->recordSpan($this->makeSpan('mid'));
        $recorder->recordSpan($this->makeSpan('new'));

        $names = array_map(static fn(Span $s): string => $s->name, $recorder->spans());
        self::assertNotContains('old', $names, 'cap-hit drops the oldest when no exporter is bound');
    }

    public function testFlushIsANoopWhenNothingIsBuffered(): void
    {
        $exporter = $this->fakeExporter();
        $recorder = new InMemoryRecorder($exporter);

        $recorder->flush();

        self::assertSame(0, $exporter->callCount);
    }

    private function makeSpan(string $name): Span
    {
        return new Span(
            SpanContext::root(),
            $name,
            SpanKind::Internal,
            1,
            2,
            SpanStatus::Ok,
        );
    }

    private function makePoint(string $name): MetricPoint
    {
        return new MetricPoint($name, MetricKind::Counter, 1.0, 1);
    }

    private function fakeExporter(): ExporterInterface
    {
        return new class implements ExporterInterface {
            public int $callCount = 0;

            /** @var list<Span> */
            public array $lastSpans = [];

            /** @var list<MetricPoint> */
            public array $lastMetrics = [];

            #[Override]
            public function export(array $spans, array $metrics): void
            {
                ++$this->callCount;
                $this->lastSpans = $spans;
                $this->lastMetrics = $metrics;
            }
        };
    }
}
