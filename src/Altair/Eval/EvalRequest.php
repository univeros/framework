<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval;

/**
 * One sandboxed evaluation: the snippet, the guard-rail toggles, and the host
 * project root. Construction clamps every limit into a safe range so a caller
 * cannot ask for an unbounded timeout, an unbounded memory cap, or a
 * negative deadline.
 */
final readonly class EvalRequest
{
    public const int DEFAULT_TIMEOUT_MS = 5_000;

    public const int MAX_TIMEOUT_MS = 60_000;

    public const int MIN_TIMEOUT_MS = 100;

    public const int DEFAULT_MEMORY_MB = 128;

    public const int MAX_MEMORY_MB = 512;

    public const int MIN_MEMORY_MB = 16;

    public int $timeoutMs;

    public int $memoryLimitMb;

    public function __construct(
        public string $snippet,
        public string $projectRoot,
        int $timeoutMs = self::DEFAULT_TIMEOUT_MS,
        int $memoryLimitMb = self::DEFAULT_MEMORY_MB,
        public bool $allowWrites = false,
        public bool $allowNetwork = false,
        public bool $unsafe = false,
        public ?string $bootstrap = null,
    ) {
        $this->timeoutMs = max(self::MIN_TIMEOUT_MS, min(self::MAX_TIMEOUT_MS, $timeoutMs));
        $this->memoryLimitMb = max(self::MIN_MEMORY_MB, min(self::MAX_MEMORY_MB, $memoryLimitMb));
    }
}
