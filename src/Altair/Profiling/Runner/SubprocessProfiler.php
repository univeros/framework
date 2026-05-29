<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Runner;

use Altair\Profiling\Exception\SamplerUnavailableException;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Model\Sample;
use Altair\Profiling\Model\SampleLog;
use Altair\Profiling\Profiler;
use Altair\Profiling\Sampler\ExcimerSampler;
use Altair\Profiling\Tree\HotspotAnalyzer;

/**
 * Profiles a separate PHP invocation by spawning it with an `auto_prepend_file`
 * that boots excimer at start-up and serialises the sample log on shutdown.
 *
 * The parent does not need excimer loaded itself: it reads the JSON-serialised
 * samples back, reconstructs a {@see SampleLog}, and folds it through the same
 * {@see Profiler::fromSampleLog()} path the in-process profiler uses. So a
 * developer machine without excimer can still *render* profiles produced on a
 * machine that does have it, and an MCP host can collect profiles from a
 * subprocess without touching its own loaded modules.
 */
final readonly class SubprocessProfiler
{
    public const int DEFAULT_PERIOD_US = ExcimerSampler::DEFAULT_PERIOD_US;

    public function __construct(
        private string $projectRoot,
        private PrependBuilder $builder = new PrependBuilder(),
        private string $phpBinary = 'php',
    ) {}

    /**
     * @param list<string> $command argv form WITHOUT the `php` prefix (e.g. ['bin/altair', 'doctor'])
     */
    public function run(array $command, string $description, int $periodUs = self::DEFAULT_PERIOD_US, int $timeoutMs = 60_000, int $hotspotLimit = HotspotAnalyzer::DEFAULT_LIMIT): ProfileReport
    {
        $tempDir = $this->prepareTempDir();
        $prependFile = $tempDir . '/prepend.php';
        $outputFile = $tempDir . '/samples.json';

        try {
            file_put_contents($prependFile, $this->builder->build($outputFile, $periodUs));
            touch($outputFile);

            $argv = [
                $this->phpBinary,
                '-d', 'auto_prepend_file=' . $prependFile,
                ...$command,
            ];

            $start = hrtime(true);
            $this->spawn($argv, $timeoutMs);
            $durationMs = (int) ((hrtime(true) - $start) / 1_000_000);

            $samples = $this->readSamples($outputFile);
            $log = new SampleLog($samples, $periodUs, 'excimer');

            if ($samples === []) {
                throw SamplerUnavailableException::noBackend();
            }

            return (new Profiler(new ExcimerSampler($periodUs)))->fromSampleLog($log, $description, $durationMs, $hotspotLimit);
        } finally {
            $this->cleanup($tempDir);
        }
    }

    /**
     * @param list<string> $argv
     */
    private function spawn(array $argv, int $timeoutMs): void
    {
        $process = proc_open($argv, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->projectRoot);
        if (!\is_resource($process)) {
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = microtime(true) + $timeoutMs / 1_000;
        while (true) {
            $status = proc_get_status($process);
            $this->drain($pipes);
            if (!$status['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                @proc_terminate($process, 15);
                usleep(100_000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    @proc_terminate($process, 9);
                }

                break;
            }

            $read = array_values(array_filter([$pipes[1] ?? null, $pipes[2] ?? null], \is_resource(...)));
            if ($read === []) {
                usleep(10_000);

                continue;
            }

            $write = null;
            $except = null;
            @stream_select($read, $write, $except, 0, 200_000);
        }

        foreach ($pipes as $pipe) {
            if (\is_resource($pipe)) {
                fclose($pipe);
            }
        }

        proc_close($process);
    }

    /**
     * Discard whatever stdout/stderr the subprocess emits — we don't surface
     * the output through the profile, only the captured samples. Reading is
     * non-blocking, so this returns immediately if nothing is buffered.
     *
     * @param array<int, resource> $pipes
     */
    private function drain(array $pipes): void
    {
        foreach ([1, 2] as $fd) {
            if (!\is_resource($pipes[$fd] ?? null)) {
                continue;
            }

            while (($chunk = fread($pipes[$fd], 8192)) !== false && $chunk !== '') {
                // intentional: discard
            }
        }
    }

    /**
     * @return list<Sample>
     */
    private function readSamples(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }

        $contents = (string) file_get_contents($file);
        if ($contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (!\is_array($decoded)) {
            return [];
        }

        $samples = [];
        foreach ($decoded as $entry) {
            if (!\is_array($entry)) {
                continue;
            }

            if (!\is_array($entry['trace'] ?? null)) {
                continue;
            }

            /** @var list<array<string, mixed>> $trace */
            $trace = array_values($entry['trace']);
            $samples[] = new Sample($this->stackFromTrace($trace), (int) ($entry['count'] ?? 1));
        }

        return $samples;
    }

    /**
     * @param list<array<string, mixed>> $trace leaf-first
     *
     * @return list<string> root-first
     */
    private function stackFromTrace(array $trace): array
    {
        $stack = [];
        foreach (array_reverse($trace) as $frame) {
            $function = isset($frame['function']) ? (string) $frame['function'] : '<unknown>';
            $stack[] = isset($frame['class']) ? $frame['class'] . '::' . $function : $function;
        }

        return $stack;
    }

    private function prepareTempDir(): string
    {
        $base = $this->projectRoot . '/.altair/profiles/tmp';
        if (!is_dir($base)) {
            mkdir($base, 0o755, true);
        }

        $dir = $base . '/' . uniqid('', true);
        mkdir($dir, 0o755, true);

        return $dir;
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($dir);
    }
}
