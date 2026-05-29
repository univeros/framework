<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Profiling\Diff\Differ;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Output\HumanRenderer;
use Altair\Profiling\Support\Json;
use Altair\Profiling\Support\Workspace;

/**
 * `bin/altair profile:compare <base-id> <head-id>` — diff two stored profiles
 * function-by-function, surface regressions, and exit `1` if any are found
 * (CI gate). The killer feature for refactor confidence: profile before, make
 * the change, profile after, compare.
 */
#[Command(
    name: 'profile:compare',
    description: 'Compare two stored profiles and flag regressions; exit 1 if any regressions are found.',
)]
final readonly class CompareCommand
{
    use Workspace;

    public function __invoke(
        #[Argument(description: 'Baseline profile id.')]
        string $base,
        #[Argument(description: 'HEAD profile id (the candidate run to assess).')]
        string $head,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $storage = $this->storage();

        $baseReport = $storage->load($base);
        $headReport = $storage->load($head);

        if (!$baseReport instanceof ProfileReport || !$headReport instanceof ProfileReport) {
            echo "Profile not found: " . ($baseReport instanceof ProfileReport ? $head : $base) . "\n";

            return 2;
        }

        $diff = (new Differ())->diff($baseReport, $headReport);

        echo $format === 'json'
            ? Json::encode($diff->toArray())
            : (new HumanRenderer())->diff($diff);

        return $diff->hasRegressions() ? 1 : 0;
    }
}
