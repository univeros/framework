<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Suggest\Exception\SuggestException;
use Altair\Suggest\Output\RendererRegistry;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\SuggestionEngine;

/**
 * `bin/altair suggest` — walk the introspection surface and propose refactors.
 *
 * Read-only: it builds a structural snapshot and reasons over it, never
 * mutating anything. Exit code is `1` when any warning-level suggestion is
 * shown (so CI can gate on `--severity=warning`), otherwise `0`.
 */
#[Command(
    name: 'suggest',
    description: 'Suggest refactors from introspection: dead bindings/events, fat constructors, routes without specs, orphan middleware.',
)]
final readonly class SuggestCommand
{
    public function __construct(
        private SnapshotFactory $snapshots,
        private SuggestionEngine $engine,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Minimum severity to report: info (all) or warning.')]
        string $severity = 'info',
        #[Option(description: 'Comma-separated rule names to run exclusively.')]
        ?string $only = null,
        #[Option(description: 'Comma-separated rule names to skip.')]
        ?string $skip = null,
    ): int {
        try {
            $minimum = Severity::fromName($severity);
            $renderer = $this->renderers->get($format);
        } catch (SuggestException $suggestException) {
            echo $suggestException->getMessage(), "\n";

            return 2;
        }

        $report = $this->engine->analyse(
            $this->snapshots->create(),
            $minimum,
            $this->csv($only),
            $this->csv($skip),
        );

        echo $renderer->render($report);

        return $report->exitCode();
    }

    /**
     * @return list<string>
     */
    private function csv(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $value)),
            static fn(string $item): bool => $item !== '',
        ));
    }
}
