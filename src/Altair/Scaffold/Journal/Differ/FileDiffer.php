<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Differ;

/**
 * Minimal pure-PHP unified-diff producer.
 *
 * The journal needs diffs only for embedding (so rewind can restore the
 * before-state). It does NOT need to apply diffs in reverse — rewind
 * uses the embedded full `content_before` field instead, which is
 * always byte-correct and never ambiguous. The diff here is for human
 * readability inside `spec diff <id>` output.
 *
 * Output format: standard `@@ -A,B +C,D @@` hunks with context lines.
 * Not a perfect match for GNU diff but close enough to read.
 */
final readonly class FileDiffer
{
    public function __construct(
        private int $contextLines = 3,
    ) {}

    public function diff(string $before, string $after, string $beforeLabel = 'before', string $afterLabel = 'after'): string
    {
        if ($before === $after) {
            return '';
        }

        $a = $before === '' ? [] : explode("\n", $before);
        $b = $after === '' ? [] : explode("\n", $after);

        $lcs = $this->lcsTable($a, $b);
        $ops = $this->backtrack($lcs, $a, $b, \count($a), \count($b));

        return "--- {$beforeLabel}\n+++ {$afterLabel}\n" . $this->renderHunks($ops);
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     * @return list<list<int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $m = \count($a);
        $n = \count($b);
        $t = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $t[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $t[$i - 1][$j - 1] + 1
                    : max($t[$i - 1][$j], $t[$i][$j - 1]);
            }
        }

        return $t;
    }

    /**
     * @param list<list<int>> $t
     * @param list<string>    $a
     * @param list<string>    $b
     *
     * @return list<array{op: '='|'-'|'+', a: int, b: int, line: string}>
     */
    private function backtrack(array $t, array $a, array $b, int $i, int $j): array
    {
        $ops = [];
        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                $ops[] = ['op' => '=', 'a' => $i, 'b' => $j, 'line' => $a[$i - 1]];
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $t[$i][$j - 1] >= $t[$i - 1][$j])) {
                $ops[] = ['op' => '+', 'a' => $i, 'b' => $j, 'line' => $b[$j - 1]];
                $j--;
            } else {
                $ops[] = ['op' => '-', 'a' => $i, 'b' => $j, 'line' => $a[$i - 1]];
                $i--;
            }
        }

        return array_reverse($ops);
    }

    /**
     * @param list<array{op: '='|'-'|'+', a: int, b: int, line: string}> $ops
     */
    private function renderHunks(array $ops): string
    {
        $count = \count($ops);
        if ($count === 0) {
            return '';
        }

        $changeIdx = [];
        foreach ($ops as $i => $op) {
            if ($op['op'] !== '=') {
                $changeIdx[] = $i;
            }
        }

        if ($changeIdx === []) {
            return '';
        }

        $context = $this->contextLines;
        $output = '';
        $cursor = 0;

        while ($cursor < \count($changeIdx)) {
            $start = max(0, $changeIdx[$cursor] - $context);
            $end = min($count - 1, $changeIdx[$cursor] + $context);

            // Extend the hunk while subsequent changes are within `2*context` lines.
            while ($cursor + 1 < \count($changeIdx) && $changeIdx[$cursor + 1] - $end <= $context) {
                $cursor++;
                $end = min($count - 1, $changeIdx[$cursor] + $context);
            }

            $output .= $this->renderHunk($ops, $start, $end);
            $cursor++;
        }

        return $output;
    }

    /**
     * @param list<array{op: '='|'-'|'+', a: int, b: int, line: string}> $ops
     */
    private function renderHunk(array $ops, int $start, int $end): string
    {
        $aStart = null;
        $bStart = null;
        $aCount = 0;
        $bCount = 0;
        $body = '';

        for ($i = $start; $i <= $end; $i++) {
            $op = $ops[$i];
            $aLine = $op['a'];
            $bLine = $op['b'];
            if ($op['op'] === '=') {
                $aStart ??= max(1, $aLine - $aCount);
                $bStart ??= max(1, $bLine - $bCount);
                $aCount++;
                $bCount++;
                $body .= ' ' . $op['line'] . "\n";
            } elseif ($op['op'] === '-') {
                $aStart ??= max(1, $aLine - $aCount);
                $bStart ??= max(1, $bLine - $bCount + 1);
                $aCount++;
                $body .= '-' . $op['line'] . "\n";
            } else {
                $aStart ??= max(1, $aLine - $aCount + 1);
                $bStart ??= max(1, $bLine - $bCount);
                $bCount++;
                $body .= '+' . $op['line'] . "\n";
            }
        }

        $aStart ??= 1;
        $bStart ??= 1;

        return \sprintf("@@ -%d,%d +%d,%d @@\n%s", $aStart, $aCount, $bStart, $bCount, $body);
    }
}
