<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Output;

use Altair\Profiling\Model\CallNode;
use Altair\Profiling\Model\ProfileReport;

/**
 * Renders a call tree as a (Brendan-Gregg-style) inline SVG flamegraph: one
 * row per depth, each frame a rectangle whose width is proportional to its
 * total samples and whose horizontal position reflects its place in the tree.
 *
 * Bottom row is the {@see ProfileReport}'s root frame; subsequent rows stack
 * UPWARD (lower y = deeper). Frame text is XML-escaped; an empty tree
 * produces a valid placeholder SVG rather than malformed output.
 */
final class FlamegraphRenderer
{
    public const int ROW_HEIGHT_PX = 18;

    public const int WIDTH_PX = 1200;

    private const array PALETTE = [
        '#e76f51', '#e9c46a', '#2a9d8f', '#264653', '#f4a261',
        '#8338ec', '#ff006e', '#3a86ff', '#fb5607', '#06d6a0',
    ];

    public function render(ProfileReport $report): string
    {
        $totalWidth = max(1, $report->tree->totalSamples + $report->tree->selfSamples);
        if ($report->tree->totalSamples === 0 && $report->tree->selfSamples === 0) {
            $totalWidth = max(1, $this->sumChildrenTotal($report->tree));
        }

        $depth = $this->maxDepth($report->tree);
        $heightRows = max(1, $depth);
        $heightPx = $heightRows * self::ROW_HEIGHT_PX + 40;

        $title = htmlspecialchars(
            \sprintf('%s — %d samples, %d ms', $report->target, $report->totalSamples, $report->durationMs),
            ENT_QUOTES | ENT_XML1,
        );

        $svg = [];
        $svg[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg[] = \sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" font-family="Menlo, monospace" font-size="11">',
            self::WIDTH_PX,
            $heightPx,
        );
        $svg[] = \sprintf('<title>%s</title>', $title);
        $svg[] = \sprintf('<text x="6" y="20" font-weight="bold">%s</text>', $title);

        foreach ($report->tree->children as $child) {
            $this->drawNode($svg, $child, depth: 0, xOffset: 0, totalWidth: $totalWidth, baseY: $heightPx - self::ROW_HEIGHT_PX);
        }

        $svg[] = '</svg>';

        return implode("\n", $svg) . "\n";
    }

    /**
     * @param list<string> $svg
     */
    private function drawNode(array &$svg, CallNode $node, int $depth, float $xOffset, int $totalWidth, int $baseY): void
    {
        $widthPx = $node->totalSamples / $totalWidth * self::WIDTH_PX;
        if ($widthPx < 0.5) {
            return; // too narrow to render meaningfully
        }

        $y = $baseY - $depth * self::ROW_HEIGHT_PX;
        $colour = self::PALETTE[crc32($node->name) % \count(self::PALETTE)];

        $svg[] = \sprintf(
            '<rect x="%.2f" y="%d" width="%.2f" height="%d" fill="%s" stroke="#fff" stroke-width="0.5"><title>%s — %d samples (%.1f%%)</title></rect>',
            $xOffset,
            $y,
            $widthPx,
            self::ROW_HEIGHT_PX,
            $colour,
            htmlspecialchars($node->name, ENT_QUOTES | ENT_XML1),
            $node->totalSamples,
            $node->totalSamples / $totalWidth * 100,
        );

        if ($widthPx > 60) {
            $label = htmlspecialchars($this->truncateLabel($node->name, (int) ($widthPx / 7)), ENT_QUOTES | ENT_XML1);
            $svg[] = \sprintf(
                '<text x="%.2f" y="%d" fill="#fff">%s</text>',
                $xOffset + 4,
                $y + self::ROW_HEIGHT_PX - 5,
                $label,
            );
        }

        $childX = $xOffset;
        foreach ($node->children as $child) {
            $this->drawNode($svg, $child, $depth + 1, $childX, $totalWidth, $baseY);
            $childX += $child->totalSamples / $totalWidth * self::WIDTH_PX;
        }
    }

    private function sumChildrenTotal(CallNode $node): int
    {
        $sum = 0;
        foreach ($node->children as $child) {
            $sum += $child->totalSamples;
        }

        return $sum;
    }

    private function maxDepth(CallNode $node): int
    {
        if ($node->children === []) {
            return 1;
        }

        $deepest = 0;
        foreach ($node->children as $child) {
            $deepest = max($deepest, $this->maxDepth($child));
        }

        return 1 + $deepest;
    }

    private function truncateLabel(string $label, int $maxChars): string
    {
        if ($maxChars <= 1 || \strlen($label) <= $maxChars) {
            return $label;
        }

        return substr($label, 0, max(1, $maxChars - 1)) . '…';
    }
}
