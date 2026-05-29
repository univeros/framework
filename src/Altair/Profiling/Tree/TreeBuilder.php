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
use Altair\Profiling\Model\Sample;

/**
 * Folds a list of root-first sample stacks into one weighted call tree.
 *
 * Two passes: the first walks the samples into a mutable nested-array trie
 * (children keyed by frame name, each carrying running self/total counters);
 * the second freezes that trie into a tree of {@see CallNode}, sorting each
 * node's children by total samples descending so the hottest path reads first.
 */
final readonly class TreeBuilder
{
    /**
     * @param list<Sample> $samples
     */
    public function build(array $samples, string $rootName = '<root>'): CallNode
    {
        $root = ['name' => $rootName, 'self' => 0, 'total' => 0, 'children' => []];

        foreach ($samples as $sample) {
            if ($sample->stack === []) {
                $root['self'] += $sample->count;
                $root['total'] += $sample->count;

                continue;
            }

            $this->addStack($root, $sample->stack, $sample->count);
        }

        return $this->freeze($root);
    }

    /**
     * @param array{name: string, self: int, total: int, children: array<string, array<string, mixed>>} $node
     * @param list<string>                                                                              $stack
     */
    private function addStack(array &$node, array $stack, int $count): void
    {
        $node['total'] += $count;
        $cursor = &$node;

        foreach ($stack as $i => $frame) {
            if (!isset($cursor['children'][$frame])) {
                $cursor['children'][$frame] = ['name' => $frame, 'self' => 0, 'total' => 0, 'children' => []];
            }

            /** @var array{name: string, self: int, total: int, children: array<string, array<string, mixed>>} $child */
            $child = &$cursor['children'][$frame];
            $child['total'] += $count;

            if ($i === \count($stack) - 1) {
                $child['self'] += $count;
            }

            $cursor = &$child;
        }

        unset($cursor);
    }

    /**
     * @param array{name: string, self: int, total: int, children: array<string, array<string, mixed>>} $node
     */
    private function freeze(array $node): CallNode
    {
        $children = [];
        foreach ($node['children'] as $child) {
            /** @var array{name: string, self: int, total: int, children: array<string, array<string, mixed>>} $child */
            $children[] = $this->freeze($child);
        }

        usort($children, static fn(CallNode $a, CallNode $b): int => $b->totalSamples <=> $a->totalSamples ?: strcmp($a->name, $b->name));

        return new CallNode($node['name'], $node['self'], $node['total'], $children);
    }
}
