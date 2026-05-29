<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Storage;

/**
 * Lightweight projection of a stored profile for the `profile:list` view —
 * just the metadata, not the full tree, so listing a hundred profiles never
 * reads megabytes of JSON.
 */
final readonly class ProfileSummary
{
    public function __construct(
        public string $id,
        public string $target,
        public string $createdAt,
        public int $totalSamples,
        public int $durationMs,
        public string $backend,
    ) {}

    /**
     * @return array{id: string, target: string, created_at: string, total_samples: int, duration_ms: int, backend: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target' => $this->target,
            'created_at' => $this->createdAt,
            'total_samples' => $this->totalSamples,
            'duration_ms' => $this->durationMs,
            'backend' => $this->backend,
        ];
    }
}
