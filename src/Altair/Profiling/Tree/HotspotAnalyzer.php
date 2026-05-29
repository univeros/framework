<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Tree;

use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\Hotspot;

/**
 * Picks the top-N functions by self-samples from a call tree, which is what
 * the optimisation loop reads — "where is time spent?" answered as a flat
 * table, with percent-of-total so two profiles are comparable.
 *
 * The walk aggregates per FUNCTION (across all call sites): the same method
 * called from three different parents collapses into one hotspot row whose
 * self/total samples are summed. That matches what a human asks ("how much
 * time is in `Mapper::queueCreate`?") rather than what the tree expresses
 * ("how much time is in *this path through* `Mapper::queueCreate`?").
 */
final readonly class HotspotAnalyzer
{
    public const int DEFAULT_LIMIT = 10;

    /**
     * @return list<Hotspot>
     */
    public function analyse(CallNode $tree, int $limit = self::DEFAULT_LIMIT): array
    {
        /** @var array<string, array{self: int, total: int}> $aggregate */
        $aggregate = [];
        $this->walk($tree, $aggregate, isRoot: true);

        $totalSelf = 0;
        foreach ($aggregate as $row) {
            $totalSelf += $row['self'];
        }

        $rows = [];
        foreach ($aggregate as $function => $row) {
            $percent = $totalSelf === 0 ? 0.0 : round($row['self'] / $totalSelf * 100, 2);
            $rows[] = new Hotspot($function, $row['self'], $row['total'], $percent);
        }

        usort(
            $rows,
            static fn(Hotspot $a, Hotspot $b): int => $b->selfSamples <=> $a->selfSamples
                ?: strcmp($a->function, $b->function),
        );

        return \array_slice($rows, 0, max(0, $limit));
    }

    /**
     * @param array<string, array{self: int, total: int}> $aggregate
     */
    private function walk(CallNode $node, array &$aggregate, bool $isRoot): void
    {
        if (!$isRoot) {
            $aggregate[$node->name] ??= ['self' => 0, 'total' => 0];
            $aggregate[$node->name]['self'] += $node->selfSamples;
            $aggregate[$node->name]['total'] += $node->totalSamples;
        }

        foreach ($node->children as $child) {
            $this->walk($child, $aggregate, isRoot: false);
        }
    }
}
