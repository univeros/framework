<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Configuration;

use Altair\Configuration\Support\Env;

use const DIRECTORY_SEPARATOR;

/**
 * Resolved configuration for the event log, parsed once from env.
 *
 * | Variable                          | Default                 | Purpose                                          |
 * |-----------------------------------|-------------------------|--------------------------------------------------|
 * | ALTAIR_EVENTS_ENABLED             | `true`                  | Set `false` to bind {@see NullRecorder}.         |
 * | ALTAIR_EVENTS_DIR                 | `.altair`               | Base directory (relative to project root).       |
 * | ALTAIR_EVENTS_LOG_FILE            | `events.jsonl`          | Log filename inside the base directory.          |
 * | ALTAIR_EVENTS_SNAPSHOTS_DIR       | `snapshots`             | Snapshot subdirectory.                           |
 * | ALTAIR_EVENTS_CHECKPOINTS_DIR     | `checkpoints`           | Checkpoints subdirectory.                        |
 * | ALTAIR_EVENTS_EXTRA_SECRET_FLAGS  | (empty)                 | Comma-separated additional flag names to redact. |
 */
final readonly class EventsSettings
{
    /**
     * @param list<string> $extraSecretFlags
     */
    public function __construct(
        public bool $enabled,
        public string $projectRoot,
        public string $baseDirectory,
        public string $logFileName,
        public string $snapshotsDirectory,
        public string $checkpointsDirectory,
        public array $extraSecretFlags = [],
    ) {}

    public static function fromEnv(Env $env, ?string $projectRoot = null): self
    {
        $root = $projectRoot ?? self::guessProjectRoot();

        return new self(
            enabled: self::boolFromEnv($env->get('ALTAIR_EVENTS_ENABLED', 'true')),
            projectRoot: $root,
            baseDirectory: (string) $env->get('ALTAIR_EVENTS_DIR', '.altair'),
            logFileName: (string) $env->get('ALTAIR_EVENTS_LOG_FILE', 'events.jsonl'),
            snapshotsDirectory: (string) $env->get('ALTAIR_EVENTS_SNAPSHOTS_DIR', 'snapshots'),
            checkpointsDirectory: (string) $env->get('ALTAIR_EVENTS_CHECKPOINTS_DIR', 'checkpoints'),
            extraSecretFlags: self::listFromEnv($env->get('ALTAIR_EVENTS_EXTRA_SECRET_FLAGS', '')),
        );
    }

    public function logPath(): string
    {
        return $this->joinPath($this->projectRoot, $this->baseDirectory, $this->logFileName);
    }

    public function snapshotsPath(): string
    {
        return $this->joinPath($this->projectRoot, $this->baseDirectory, $this->snapshotsDirectory);
    }

    public function checkpointsPath(): string
    {
        return $this->joinPath($this->projectRoot, $this->baseDirectory, $this->checkpointsDirectory);
    }

    private function joinPath(string ...$parts): string
    {
        $clean = array_map(static fn(string $p): string => rtrim($p, '/\\'), $parts);

        return implode(DIRECTORY_SEPARATOR, $clean);
    }

    private static function boolFromEnv(mixed $raw): bool
    {
        if (\is_bool($raw)) {
            return $raw;
        }

        return !\in_array(strtolower((string) $raw), ['0', 'false', 'off', 'no', ''], true);
    }

    /**
     * @return list<string>
     */
    private static function listFromEnv(mixed $raw): array
    {
        if (!\is_string($raw) || trim($raw) === '') {
            return [];
        }

        $items = array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== '');

        return array_values($items);
    }

    private static function guessProjectRoot(): string
    {
        $cwd = getcwd();

        return $cwd === false ? '.' : $cwd;
    }
}
