<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observability\Metrics;

use Altair\Observability\Metrics\Meter;
use Altair\Observability\Metrics\MetricKind;
use Altair\Observability\Metrics\MetricPoint;
use Altair\Observability\Recorder\InMemoryRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Meter::class)]
#[CoversClass(MetricPoint::class)]
final class MeterTest extends TestCase
{
    public function testCounterRecordsACounterPoint(): void
    {
        $recorder = new InMemoryRecorder();
        (new Meter($recorder))->counter('requests', 1.0, ['http.method' => 'GET']);

        $point = $recorder->metrics()[0];
        self::assertSame(MetricKind::Counter, $point->kind);
        self::assertSame(1.0, $point->value);
        self::assertSame('GET', $point->attributes['http.method']);
    }

    public function testGaugeAndHistogramKindsAreSetCorrectly(): void
    {
        $recorder = new InMemoryRecorder();
        $meter = new Meter($recorder);

        $meter->gauge('memory_in_use', 4_194_304.0, unit: 'By');
        $meter->histogram('request_duration', 12.5, unit: 'ms');

        self::assertSame(MetricKind::Gauge, $recorder->metrics()[0]->kind);
        self::assertSame('By', $recorder->metrics()[0]->unit);
        self::assertSame(MetricKind::Histogram, $recorder->metrics()[1]->kind);
        self::assertSame(12.5, $recorder->metrics()[1]->value);
    }

    public function testTimestampsAreNonZeroAndRoughlyNow(): void
    {
        $recorder = new InMemoryRecorder();
        (new Meter($recorder))->counter('x');

        $nowNs = (int) (microtime(true) * 1_000_000_000);
        $point = $recorder->metrics()[0];

        self::assertGreaterThan(0, $point->unixNano);
        self::assertLessThan(1_000_000_000, abs($nowNs - $point->unixNano), 'timestamp should be within 1s of "now"');
    }
}
