<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling;

use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Model\SampleLog;
use Altair\Profiling\Sampler\Contracts\SamplerInterface;
use Altair\Profiling\Tree\HotspotAnalyzer;
use Altair\Profiling\Tree\TreeBuilder;

/**
 * In-process profiler: wraps a callable in start/stop sampler calls and folds
 * the captured samples into a {@see ProfileReport}.
 *
 * Useful as a library API (`$profiler->profile(fn() => $svc->run(), 'svc.run')`)
 * and as the inner engine for the CLI subprocess path. The Profiler does not
 * persist anything — the caller passes the resulting report to a storage if
 * they want it kept.
 */
final readonly class Profiler
{
    public function __construct(
        private SamplerInterface $sampler,
        private TreeBuilder $treeBuilder = new TreeBuilder(),
        private HotspotAnalyzer $hotspots = new HotspotAnalyzer(),
    ) {}

    public function profile(callable $target, string $description, int $hotspotLimit = HotspotAnalyzer::DEFAULT_LIMIT): ProfileReport
    {
        $startNs = hrtime(true);
        $this->sampler->start();
        try {
            $target();
        } finally {
            $log = $this->sampler->stop();
            $durationMs = (int) ((hrtime(true) - $startNs) / 1_000_000);
        }

        return $this->buildReport($log, $description, $durationMs, $hotspotLimit);
    }

    public function fromSampleLog(SampleLog $log, string $description, int $durationMs, int $hotspotLimit = HotspotAnalyzer::DEFAULT_LIMIT): ProfileReport
    {
        return $this->buildReport($log, $description, $durationMs, $hotspotLimit);
    }

    private function buildReport(SampleLog $log, string $description, int $durationMs, int $hotspotLimit): ProfileReport
    {
        $tree = $this->treeBuilder->build($log->samples);
        $hotspots = $this->hotspots->analyse($tree, $hotspotLimit);

        return new ProfileReport(
            $this->generateId(),
            $description,
            date(DATE_ATOM),
            $log->totalSamples(),
            $durationMs,
            $log->periodUs,
            $log->backend,
            $tree,
            $hotspots,
        );
    }

    private function generateId(): string
    {
        return date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    }
}
