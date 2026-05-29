<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observability\Trace;

use Altair\Observability\Recorder\InMemoryRecorder;
use Altair\Observability\Trace\Span;
use Altair\Observability\Trace\SpanContext;
use Altair\Observability\Trace\SpanKind;
use Altair\Observability\Trace\SpanStatus;
use Altair\Observability\Trace\Tracer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Tracer::class)]
#[CoversClass(Span::class)]
#[CoversClass(SpanContext::class)]
#[CoversClass(InMemoryRecorder::class)]
final class TracerTest extends TestCase
{
    public function testStartCreatesARootSpanWithFreshTraceId(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        $context = $tracer->start('root');
        $tracer->end($context);

        $span = $recorder->spans()[0];
        self::assertSame('root', $span->name);
        self::assertSame(32, \strlen($span->context->traceId), 'trace id is 16 bytes hex-encoded');
        self::assertSame(16, \strlen($span->context->spanId));
        self::assertNull($span->context->parentSpanId);
    }

    public function testNestedSpansShareATraceIdAndCarryParentLinkage(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        $outer = $tracer->start('outer');
        $inner = $tracer->start('inner');
        $tracer->end($inner);
        $tracer->end($outer);

        $byName = [];
        foreach ($recorder->spans() as $span) {
            $byName[$span->name] = $span;
        }

        self::assertSame($byName['outer']->context->traceId, $byName['inner']->context->traceId);
        self::assertSame($byName['outer']->context->spanId, $byName['inner']->context->parentSpanId);
    }

    public function testEndingOutOfOrderStillPopsTheMatchingSpan(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        $a = $tracer->start('a');
        $b = $tracer->start('b');
        $tracer->end($a); // ends the inner-most-with-matching-spanId regardless of stack position
        $tracer->end($b);

        self::assertCount(2, $recorder->spans());
    }

    public function testSpanHelperRunsTheCallableAndRecordsAnOkSpan(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        $value = $tracer->span('work', fn(): int => 42);

        self::assertSame(42, $value);
        self::assertSame(SpanStatus::Ok, $recorder->spans()[0]->status);
    }

    public function testSpanHelperCapturesExceptionsAndRethrows(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        self::expectException(RuntimeException::class);

        try {
            $tracer->span('work', static fn(): never => throw new RuntimeException('boom'));
        } finally {
            self::assertSame(SpanStatus::Error, $recorder->spans()[0]->status);
            self::assertSame('boom', $recorder->spans()[0]->statusMessage);
            self::assertSame('RuntimeException', $recorder->spans()[0]->attributes['exception.type']);
        }
    }

    public function testSpanKindAndAttributesAreRecorded(): void
    {
        $tracer = new Tracer($recorder = new InMemoryRecorder());

        $context = $tracer->start('GET /x', SpanKind::Server, ['http.method' => 'GET']);
        $tracer->end($context, SpanStatus::Ok, null, ['http.status' => 200]);

        $span = $recorder->spans()[0];
        self::assertSame(SpanKind::Server, $span->kind);
        self::assertSame('GET', $span->attributes['http.method']);
        self::assertSame(200, $span->attributes['http.status']);
    }
}
