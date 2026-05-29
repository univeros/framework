<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Model;

/**
 * A complete profile: identification, timing, the aggregated call tree, and
 * the hotspot top-N — everything `profile:show` renders and `profile:compare`
 * diffs.
 */
final readonly class ProfileReport
{
    /**
     * @param list<Hotspot> $hotspots top-N by self-samples, percent of total
     */
    public function __construct(
        public string $id,
        public string $target,
        public string $createdAt,
        public int $totalSamples,
        public int $durationMs,
        public int $periodUs,
        public string $backend,
        public CallNode $tree,
        public array $hotspots,
    ) {}

    /**
     * Total self-samples weighted into wall-clock milliseconds. A statistical
     * approximation: `samples × period`.
     */
    public function estimatedMs(): int
    {
        return (int) ($this->totalSamples * $this->periodUs / 1_000);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target' => $this->target,
            'created_at' => $this->createdAt,
            'total_samples' => $this->totalSamples,
            'duration_ms' => $this->durationMs,
            'period_us' => $this->periodUs,
            'backend' => $this->backend,
            'tree' => $this->tree->toArray(),
            'hotspots' => array_map(static fn(Hotspot $h): array => $h->toArray(), $this->hotspots),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['id'],
            (string) $data['target'],
            (string) $data['created_at'],
            (int) $data['total_samples'],
            (int) $data['duration_ms'],
            (int) $data['period_us'],
            (string) $data['backend'],
            self::nodeFromArray($data['tree']),
            array_values(array_map(
                static fn(array $h): Hotspot => new Hotspot(
                    (string) $h['function'],
                    (int) $h['self_samples'],
                    (int) $h['total_samples'],
                    (float) $h['percent'],
                ),
                \is_array($data['hotspots']) ? $data['hotspots'] : [],
            )),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function nodeFromArray(array $data): CallNode
    {
        $children = [];
        foreach ((array) ($data['children'] ?? []) as $child) {
            $children[] = self::nodeFromArray((array) $child);
        }

        return new CallNode(
            (string) $data['name'],
            (int) $data['self_samples'],
            (int) $data['total_samples'],
            $children,
        );
    }
}
