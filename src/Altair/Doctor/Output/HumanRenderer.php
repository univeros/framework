<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Output;

use Altair\Doctor\Contracts\ReportRendererInterface;
use Altair\Doctor\Result\AgentAction;
use Altair\Doctor\Result\CheckResult;
use Altair\Doctor\Result\Report;
use Override;

/**
 * Scannable plain text — one line per check, the fix line directly under a
 * non-ok result, and any `run_command` agent action quoted as a shell
 * snippet. No frames or boxes (agents may tail this if the user pipes it).
 */
final readonly class HumanRenderer implements ReportRendererInterface
{
    #[Override]
    public function render(Report $report): string
    {
        $lines = [];

        foreach ($report->checks as $check) {
            $lines[] = \sprintf('[%-5s] %s — %s', $check->status->value, $check->name, $check->detail);
            $lines = [...$lines, ...$this->remediation($check)];
        }

        $lines[] = '';
        $lines[] = \sprintf(
            '%s — %d checks in %dms',
            strtoupper($report->status()->value),
            \count($report->checks),
            $report->durationMs,
        );

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return list<string>
     */
    private function remediation(CheckResult $check): array
    {
        $lines = [];

        if ($check->fix !== null) {
            $lines[] = '        fix: ' . $check->fix;
        }

        $action = $check->agentAction;
        if ($action instanceof AgentAction && $action->type === 'run_command' && isset($action->payload['command'])) {
            $lines[] = '        $ ' . $action->payload['command'];
        }

        return $lines;
    }
}
