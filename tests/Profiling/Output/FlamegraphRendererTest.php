<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Profiling\Output;

use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\Hotspot;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Model\Sample;
use Altair\Profiling\Output\FlamegraphRenderer;
use Altair\Profiling\Tree\HotspotAnalyzer;
use Altair\Profiling\Tree\TreeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlamegraphRenderer::class)]
final class FlamegraphRendererTest extends TestCase
{
    public function testRendersValidSvgWithFramesFromTree(): void
    {
        $samples = [
            new Sample(['Outer', 'Middle', 'Inner'], count: 8),
            new Sample(['Outer', 'Sibling'], count: 2),
        ];
        $tree = (new TreeBuilder())->build($samples);
        $hotspots = (new HotspotAnalyzer())->analyse($tree);
        $report = new ProfileReport(
            'svg-test',
            'demo',
            '2026-05-29T12:00:00Z',
            totalSamples: 10,
            durationMs: 10,
            periodUs: 1_000,
            backend: 'excimer',
            tree: $tree,
            hotspots: $hotspots,
        );

        $svg = (new FlamegraphRenderer())->render($report);

        self::assertStringStartsWith('<?xml', $svg);
        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('Outer', $svg);
        self::assertStringContainsString('Inner', $svg);
        // Empty-tree placeholder is NOT used here — a real tree should produce <rect>s.
        self::assertStringContainsString('<rect', $svg);
        self::assertStringEndsWith("</svg>\n", $svg);
    }

    public function testEscapesXmlSpecialCharactersInFrameNames(): void
    {
        $report = new ProfileReport(
            'esc',
            'demo & co.',
            '2026-05-29T12:00:00Z',
            totalSamples: 1,
            durationMs: 1,
            periodUs: 1_000,
            backend: 'excimer',
            tree: new CallNode('<root>', 0, 1, [
                new CallNode("Foo<Bar>::run('a&b')", 1, 1, []),
            ]),
            hotspots: [new Hotspot("Foo<Bar>::run('a&b')", 1, 1, 100.0)],
        );

        $svg = (new FlamegraphRenderer())->render($report);

        self::assertStringNotContainsString("Foo<Bar>::run('a&b')", $svg, 'raw special chars must be escaped');
        self::assertStringContainsString('Foo&lt;Bar&gt;', $svg);
    }

    public function testEmptyTreeStillEmitsAValidSvg(): void
    {
        $empty = new ProfileReport(
            'empty',
            'demo',
            '2026-05-29T12:00:00Z',
            totalSamples: 0,
            durationMs: 0,
            periodUs: 1_000,
            backend: 'excimer',
            tree: new CallNode('<root>', 0, 0, []),
            hotspots: [],
        );

        $svg = (new FlamegraphRenderer())->render($empty);

        self::assertStringContainsString('<svg', $svg);
        self::assertStringEndsWith("</svg>\n", $svg);
    }
}
