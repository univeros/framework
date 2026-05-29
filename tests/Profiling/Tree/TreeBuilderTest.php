<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Tree;

use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\Sample;
use Altair\Profiling\Tree\TreeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TreeBuilder::class)]
#[CoversClass(CallNode::class)]
#[CoversClass(Sample::class)]
final class TreeBuilderTest extends TestCase
{
    public function testIdenticalStacksAreCounted(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample(['A', 'B', 'C']),
            new Sample(['A', 'B', 'C']),
            new Sample(['A', 'B', 'C']),
        ]);

        self::assertSame(3, $tree->totalSamples);
        self::assertSame(0, $tree->selfSamples);

        $a = $tree->children[0];
        self::assertSame('A', $a->name);
        self::assertSame(3, $a->totalSamples);

        $c = $a->children[0]->children[0];
        self::assertSame('C', $c->name);
        self::assertSame(3, $c->selfSamples, 'leaf frame counts as self');
    }

    public function testDivergingChildrenSplitAtTheBranchingFrame(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample(['Root', 'A', 'X']),
            new Sample(['Root', 'A', 'X']),
            new Sample(['Root', 'A', 'Y']),
            new Sample(['Root', 'B']),
        ]);

        $root = $tree->children[0];
        self::assertSame(4, $root->totalSamples);

        $byName = [];
        foreach ($root->children as $child) {
            $byName[$child->name] = $child;
        }

        self::assertSame(3, $byName['A']->totalSamples);
        self::assertSame(1, $byName['B']->totalSamples);
        self::assertSame(1, $byName['B']->selfSamples);

        // Children are sorted by total samples descending.
        self::assertSame('A', $root->children[0]->name);
        self::assertSame('B', $root->children[1]->name);
    }

    public function testSampleWeightCountIsHonoured(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample(['A', 'B'], count: 7),
        ]);

        $a = $tree->children[0];
        self::assertSame(7, $a->totalSamples);
        self::assertSame(7, $a->children[0]->selfSamples);
    }

    public function testEmptyStackSampleAccumulatesAtTheRootAsSelfTime(): void
    {
        $tree = (new TreeBuilder())->build([
            new Sample([], count: 4),
        ]);

        self::assertSame(4, $tree->selfSamples);
        self::assertSame(4, $tree->totalSamples);
        self::assertSame([], $tree->children);
    }
}
