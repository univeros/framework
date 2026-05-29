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
 * A node in the aggregated call tree.
 *
 * `selfSamples` is the count of samples whose leaf is this frame; `totalSamples`
 * is the count of samples whose stack passes through this frame at any depth
 * (so `total = self + sum(child.total)`). Children are sorted by `totalSamples`
 * descending so the hottest path is the first to read.
 */
final readonly class CallNode
{
    /**
     * @param list<CallNode> $children
     */
    public function __construct(
        public string $name,
        public int $selfSamples,
        public int $totalSamples,
        public array $children,
    ) {}

    /**
     * @return array{name: string, self_samples: int, total_samples: int, children: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'self_samples' => $this->selfSamples,
            'total_samples' => $this->totalSamples,
            'children' => array_map(static fn(CallNode $c): array => $c->toArray(), $this->children),
        ];
    }
}
