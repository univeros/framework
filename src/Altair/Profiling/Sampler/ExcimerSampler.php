<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Sampler;

use Altair\Profiling\Exception\SamplerUnavailableException;
use Altair\Profiling\Model\Sample;
use Altair\Profiling\Model\SampleLog;
use Altair\Profiling\Sampler\Contracts\SamplerInterface;
use ExcimerLog;
use ExcimerLogEntry;
use ExcimerProfiler;
use Override;

/**
 * The `ext-excimer` sampling-profiler backend.
 *
 * Statistical sampling at a fixed wall-clock period (default 1 ms): excimer
 * walks the PHP call stack on every tick, so a profiled run produces ~1000
 * samples/second with minimal overhead. The collected log is folded into
 * leaf-first stack traces, then reversed to the root-first form the
 * {@see \Altair\Profiling\Tree\TreeBuilder} expects.
 */
final class ExcimerSampler implements SamplerInterface
{
    public const int DEFAULT_PERIOD_US = 1_000;

    private ?ExcimerProfiler $profiler = null;

    public function __construct(private readonly int $periodUs = self::DEFAULT_PERIOD_US) {}

    public static function available(): bool
    {
        return \extension_loaded('excimer');
    }

    #[Override]
    public function start(): void
    {
        if (!self::available()) {
            throw SamplerUnavailableException::noBackend();
        }

        $this->profiler = new ExcimerProfiler();
        $this->profiler->setPeriod($this->periodUs / 1_000_000);
        $this->profiler->start();
    }

    #[Override]
    public function stop(): SampleLog
    {
        if (!$this->profiler instanceof ExcimerProfiler) {
            return new SampleLog([], $this->periodUs, $this->backend());
        }

        $this->profiler->stop();
        $log = $this->profiler->getLog();
        $this->profiler = null;

        return new SampleLog($this->extractSamples($log), $this->periodUs, $this->backend());
    }

    #[Override]
    public function backend(): string
    {
        return 'excimer';
    }

    #[Override]
    public function periodUs(): int
    {
        return $this->periodUs;
    }

    /**
     * @return list<Sample>
     */
    private function extractSamples(ExcimerLog $log): array
    {
        $samples = [];
        foreach ($log as $entry) {
            if (!$entry instanceof ExcimerLogEntry) {
                continue;
            }

            /** @var list<array<string, mixed>> $trace */
            $trace = array_values($entry->getTrace());
            $samples[] = new Sample($this->stackFromTrace($trace), $entry->getEventCount());
        }

        return $samples;
    }

    /**
     * @param list<array<string, mixed>> $trace excimer's leaf-first stack
     *
     * @return list<string> root-first list of "Class::method" frames
     */
    private function stackFromTrace(array $trace): array
    {
        $stack = [];
        foreach (array_reverse($trace) as $frame) {
            $stack[] = $this->frameLabel($frame);
        }

        return $stack;
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function frameLabel(array $frame): string
    {
        $function = isset($frame['function']) ? (string) $frame['function'] : '<unknown>';

        if (isset($frame['class'])) {
            return $frame['class'] . '::' . $function;
        }

        return $function;
    }
}
