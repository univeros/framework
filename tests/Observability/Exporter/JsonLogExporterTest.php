<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observability\Exporter;

use Altair\Observability\Exporter\JsonLogExporter;
use Altair\Observability\Metrics\MetricKind;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Trace\Span;
use Altair\Observability\Trace\SpanContext;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\SpanStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonLogExporter::class)]
final class JsonLogExporterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/altair-obs-' . bin2hex(random_bytes(5));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->dir)) {
            return;
        }

        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->dir);
    }

    public function testWritesOneJsonObjectPerLineDiscriminatedByKind(): void
    {
        $exporter = new JsonLogExporter($this->dir);

        $span = new Span(SpanContext::root(), 'test', SpanKind::Server, 1, 2, SpanStatus::Ok);
        $metric = new MetricPoint('m', MetricKind::Counter, 1.0, 1);

        $exporter->export([$span], [$metric]);

        $file = $this->dir . '/' . date('Y-m-d') . '.jsonl';
        $lines = array_filter(explode("\n", (string) file_get_contents($file)));
        self::assertCount(2, $lines);

        $first = json_decode($lines[0], true);
        self::assertSame('span', $first['_kind']);
        self::assertSame('test', $first['name']);

        $second = json_decode($lines[1], true);
        self::assertSame('metric', $second['_kind']);
        self::assertSame('counter', $second['kind']);
    }

    public function testAppendsToTheSameDayFileAcrossCalls(): void
    {
        $exporter = new JsonLogExporter($this->dir);

        $exporter->export([new Span(SpanContext::root(), 'one', SpanKind::Internal, 1, 2, SpanStatus::Ok)], []);
        $exporter->export([new Span(SpanContext::root(), 'two', SpanKind::Internal, 1, 2, SpanStatus::Ok)], []);

        $file = $this->dir . '/' . date('Y-m-d') . '.jsonl';
        $lines = array_filter(explode("\n", (string) file_get_contents($file)));
        self::assertCount(2, $lines);
    }

    public function testEmptyBatchIsANoopAndDoesNotCreateAFile(): void
    {
        (new JsonLogExporter($this->dir))->export([], []);

        self::assertFileDoesNotExist($this->dir . '/' . date('Y-m-d') . '.jsonl');
    }
}
