<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Trace;

/**
 * Internal to {@see Tracer}: the bookkeeping for an open span. Frozen into a
 * {@see Span} when the tracer closes it.
 *
 * @internal
 */
final readonly class PendingSpan
{
    /**
     * @param array<string, scalar|null|list<scalar|null>> $attributes
     */
    public function __construct(
        public SpanContext $context,
        public string $name,
        public SpanKind $kind,
        public int $startUnixNano,
        public array $attributes,
    ) {}
}
