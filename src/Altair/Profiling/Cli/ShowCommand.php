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
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Output\HumanRenderer;
use Altair\Profiling\Support\Json;
use Altair\Profiling\Support\Workspace;

/**
 * `bin/altair profile:show <id>` — render a stored profile (hotspots, timing,
 * backend) in human or JSON form.
 */
#[Command(
    name: 'profile:show',
    description: 'Show a stored profile by id.',
)]
final readonly class ShowCommand
{
    use Workspace;

    public function __invoke(
        #[Argument(description: 'Profile id (as listed by `profile:list`).')]
        string $id,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $report = $this->storage()->load($id);
        if (!$report instanceof ProfileReport) {
            echo "Profile '{$id}' not found.\n";

            return 2;
        }

        echo $format === 'json'
            ? Json::encode($report->toArray())
            : (new HumanRenderer())->report($report);

        return 0;
    }
}
