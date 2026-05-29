<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Profiling\Output\HumanRenderer;
use Altair\Profiling\Storage\ProfileSummary;
use Altair\Profiling\Support\Json;
use Altair\Profiling\Support\Workspace;

/**
 * `bin/altair profile:list` — list captured profiles under .altair/profiles/,
 * newest first. Lightweight: reads each profile's header only, never the full
 * tree, so a hundred stored profiles list in milliseconds.
 */
#[Command(
    name: 'profile:list',
    description: 'List captured profiles, newest first.',
)]
final readonly class ListCommand
{
    use Workspace;

    public function __invoke(
        #[Option(description: 'Maximum number of profiles to list (default 50).')]
        int $limit = 50,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $summaries = $this->storage()->list($limit);

        echo $format === 'json'
            ? Json::encode([
                'count' => \count($summaries),
                'profiles' => array_map(static fn(ProfileSummary $s): array => $s->toArray(), $summaries),
            ])
            : (new HumanRenderer())->list($summaries);

        return 0;
    }
}
