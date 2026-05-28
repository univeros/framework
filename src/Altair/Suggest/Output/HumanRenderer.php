<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Output;

use Altair\Suggest\Contracts\SuggestionRendererInterface;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\SuggestionReport;
use Override;

/**
 * Scannable plain text — one line per suggestion, the fix hint indented
 * directly beneath it. No frames or boxes (agents may tail this if piped).
 */
final readonly class HumanRenderer implements SuggestionRendererInterface
{
    #[Override]
    public function render(SuggestionReport $report): string
    {
        if ($report->suggestions === []) {
            return "No suggestions — nothing to refactor.\n";
        }

        $lines = [];
        foreach ($report->suggestions as $suggestion) {
            $lines[] = \sprintf('[%-7s] %s — %s', $suggestion->severity->value, $suggestion->rule, $suggestion->message);
            if ($suggestion->fix !== null) {
                $lines[] = '          fix: ' . $suggestion->fix;
            }
        }

        $lines[] = '';
        $lines[] = $this->summary($report);

        return implode("\n", $lines) . "\n";
    }

    private function summary(SuggestionReport $report): string
    {
        return \sprintf(
            '%d suggestion(s) — %d warning, %d info — in %dms',
            \count($report->suggestions),
            $report->countBy(Severity::Warning),
            $report->countBy(Severity::Info),
            $report->durationMs,
        );
    }
}
