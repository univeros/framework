<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Trace;

use Altair\Observability\Contracts\RecorderInterface;
use Throwable;

/**
 * The span lifecycle: open spans on a stack, parented to whatever is
 * currently active. Closing a span pops it off and forwards the completed
 * {@see Span} to the bound {@see RecorderInterface}.
 *
 * The Tracer is per-process state, not per-request — a host should use the
 * same instance for the whole request so parent-child stays correct. The
 * provided HTTP middleware does that automatically.
 */
final class Tracer
{
    /**
     * @var list<PendingSpan>
     */
    private array $stack = [];

    public function __construct(private readonly RecorderInterface $recorder) {}

    /**
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function start(string $name, SpanKind $kind = SpanKind::Internal, array $attributes = []): SpanContext
    {
        $context = $this->stack === []
            ? SpanContext::root()
            : $this->stack[\count($this->stack) - 1]->context->child();

        $this->stack[] = new PendingSpan(
            $context,
            $name,
            $kind,
            $this->nowNs(),
            $attributes,
        );

        return $context;
    }

    /**
     * @param array<string, scalar|null|list<scalar|null>> $attributes merged into the span's attributes
     */
    public function end(SpanContext $context, SpanStatus $status = SpanStatus::Ok, ?string $message = null, array $attributes = []): void
    {
        $pending = $this->popMatching($context);
        if (!$pending instanceof PendingSpan) {
            return;
        }

        $span = new Span(
            $pending->context,
            $pending->name,
            $pending->kind,
            $pending->startUnixNano,
            $this->nowNs(),
            $status,
            [...$pending->attributes, ...$attributes],
            $message,
        );

        $this->recorder->recordSpan($span);
    }

    /**
     * Convenience: wrap a callable in start/end with automatic error capture.
     *
     * @template T
     *
     * @param callable(): T                                  $work
     * @param array<string, scalar|null|list<scalar|null>>   $attributes
     *
     * @return T
     */
    public function span(string $name, callable $work, SpanKind $kind = SpanKind::Internal, array $attributes = []): mixed
    {
        $context = $this->start($name, $kind, $attributes);

        try {
            $result = $work();
            $this->end($context, SpanStatus::Ok);

            return $result;
        } catch (Throwable $throwable) {
            $this->end($context, SpanStatus::Error, $throwable->getMessage(), [
                'exception.type' => $throwable::class,
                'exception.message' => $throwable->getMessage(),
            ]);

            throw $throwable;
        }
    }

    private function popMatching(SpanContext $context): ?PendingSpan
    {
        for ($i = \count($this->stack) - 1; $i >= 0; --$i) {
            if ($this->stack[$i]->context->spanId === $context->spanId) {
                $pending = $this->stack[$i];
                array_splice($this->stack, $i, 1);

                return $pending;
            }
        }

        return null;
    }

    private function nowNs(): int
    {
        // hrtime is monotonic in PHP; tracked alongside microtime for wall clock.
        // OTLP wants absolute Unix nanos, so anchor to microtime.
        return (int) (microtime(true) * 1_000_000_000);
    }
}
