<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Observability\Contracts\ExporterInterface;
use Altair\Observability\Contracts\RecorderInterface;
use Altair\Observability\Exporter\JsonLogExporter;
use Altair\Observability\Metrics\Meter;
use Altair\Observability\Middleware\ObservabilityMiddleware;
use Altair\Observability\Recorder\InMemoryRecorder;
use Altair\Observability\Trace\Tracer;
use Override;

/**
 * Wires the observability stack into the Container:
 *
 *  - A shared {@see InMemoryRecorder} (bound to `RecorderInterface`).
 *  - A shared {@see Tracer} and {@see Meter} using that recorder.
 *  - A default {@see JsonLogExporter} writing to `<root>/.altair/observability/`.
 *  - The {@see ObservabilityMiddleware} the HTTP pipeline can `use(...)`.
 *
 * The defaults are tuned for local dev (JSONL log only); production hosts
 * typically pass an {@see OtlpJsonExporter} via the constructor instead. The
 * recorder auto-flushes when its buffers hit their caps, so a long-running
 * worker doesn't need to call `flush()` explicitly.
 */
final readonly class ObservabilityConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $logDirectory = null,
        private ?ExporterInterface $exporter = null,
        private int $maxBufferedSpans = 1_000,
        private int $maxBufferedPoints = 5_000,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $directory = $this->logDirectory ?? getcwd() . '/.altair/observability';
        $exporter = $this->exporter ?? new JsonLogExporter($directory);
        $maxSpans = $this->maxBufferedSpans;
        $maxPoints = $this->maxBufferedPoints;

        $container
            ->factory(
                InMemoryRecorder::class,
                static fn(): InMemoryRecorder => new InMemoryRecorder($exporter, $maxSpans, $maxPoints),
            )
            ->shared();

        $container->alias(RecorderInterface::class, InMemoryRecorder::class);

        $container
            ->factory(Tracer::class, static fn(RecorderInterface $recorder): Tracer => new Tracer($recorder))
            ->shared();

        $container
            ->factory(Meter::class, static fn(RecorderInterface $recorder): Meter => new Meter($recorder))
            ->shared();

        $container
            ->factory(
                ObservabilityMiddleware::class,
                static fn(Tracer $tracer, Meter $meter): ObservabilityMiddleware => new ObservabilityMiddleware($tracer, $meter),
            )
            ->shared();
    }
}
