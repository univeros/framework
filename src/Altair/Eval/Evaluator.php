<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval;

use Altair\Eval\Runner\SecurityProfile;
use Altair\Eval\Runner\SubprocessResult;
use Altair\Eval\Runner\SubprocessRunner;
use Altair\Eval\Runner\WrapperBuilder;

/**
 * The top-level eval orchestrator.
 *
 * For one request it: prepares a one-shot temp directory inside the project's
 * `.altair/eval/` tree, generates the wrapper PHP and stages an empty result
 * file, builds the `php -d` guard flags + env from a {@see SecurityProfile},
 * spawns the subprocess under a wall-clock budget, reads the structured result
 * the wrapper wrote, and folds everything into an {@see EvalResult}. Cleanup
 * runs even if the subprocess died — the temp tree never leaks.
 *
 * The Evaluator is stateless; one instance can serve many requests
 * concurrently provided each request has its own project root.
 */
final readonly class Evaluator
{
    public function __construct(
        private SubprocessRunner $runner = new SubprocessRunner(),
        private WrapperBuilder $builder = new WrapperBuilder(),
        private string $phpBinary = 'php',
    ) {}

    public function evaluate(EvalRequest $request): EvalResult
    {
        $temp = $this->prepareTempDir($request->projectRoot);
        $wrapper = $temp . '/wrapper.php';
        $resultFile = $temp . '/result.json';
        $snippetFile = $temp . '/snippet.php';

        try {
            file_put_contents($snippetFile, "<?php\n" . $request->snippet);
            file_put_contents($wrapper, $this->builder->build($request, $resultFile, $snippetFile));
            touch($resultFile);

            $profile = new SecurityProfile($request);
            $allowedBase = realpath($request->projectRoot);
            $command = [
                $this->phpBinary,
                ...$profile->phpFlags(\is_string($allowedBase) ? $allowedBase : $request->projectRoot),
                $wrapper,
            ];

            $sub = $this->runner->run(
                $command,
                $request->projectRoot,
                $this->mergeEnv($profile->envVars()),
                $request->timeoutMs,
            );

            return $this->buildResult($this->readPayload($resultFile), $sub);
        } finally {
            $this->cleanup($temp);
        }
    }

    private function prepareTempDir(string $projectRoot): string
    {
        $base = $projectRoot . '/.altair/eval';
        if (!is_dir($base)) {
            mkdir($base, 0o700, true);
        }

        $dir = $base . '/' . uniqid('', true);
        mkdir($dir, 0o700, true);

        return $dir;
    }

    /**
     * Inherit the parent's environment (PATH, locale, DB_*, etc.) so the
     * snippet's container resolves the host's real services, then layer the
     * eval-specific guards on top.
     *
     * @param array<string, string> $extra
     *
     * @return array<string, string>
     */
    private function mergeEnv(array $extra): array
    {
        return [...getenv(), ...$extra];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readPayload(string $resultFile): ?array
    {
        if (!is_file($resultFile)) {
            return null;
        }

        $contents = (string) file_get_contents($resultFile);
        if ($contents === '') {
            return null;
        }

        $decoded = json_decode($contents, true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function buildResult(?array $payload, SubprocessResult $sub): EvalResult
    {
        $result = isset($payload['result']) && \is_array($payload['result']) ? $payload['result'] : null;
        $exception = isset($payload['exception']) && \is_array($payload['exception']) ? $payload['exception'] : null;
        $stdout = isset($payload['stdout']) && \is_string($payload['stdout']) ? $payload['stdout'] : $sub->stdout;
        $duration = isset($payload['duration_ms']) && \is_int($payload['duration_ms']) ? $payload['duration_ms'] : $sub->durationMs;
        $memoryPeak = isset($payload['memory_peak_bytes']) && \is_int($payload['memory_peak_bytes']) ? $payload['memory_peak_bytes'] : 0;

        return new EvalResult(
            $result,
            $stdout,
            $sub->stderr,
            $exception,
            $duration,
            $memoryPeak,
            $sub->exitCode,
            $sub->timedOut,
        );
    }

    private function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            @rmdir($dir);

            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            if (is_dir($path) && !is_link($path)) {
                $this->cleanup($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
