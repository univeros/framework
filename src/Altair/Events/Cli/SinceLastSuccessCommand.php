<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Reader;

/**
 * `bin/altair events:since-last-success` — every event since the most
 * recent OK event, newest first. The agent's most common "what was I
 * just doing?" query.
 */
#[Command(
    name: 'events:since-last-success',
    description: 'Print events recorded since the most recent successful event.',
)]
final readonly class SinceLastSuccessCommand
{
    public function __construct(
        private Reader $reader,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $any = false;
        foreach ($this->reader->sinceLastSuccess() as $event) {
            $any = true;
            echo $format === 'json'
                ? $this->renderer->eventJson($event) . "\n"
                : $this->renderer->eventLineHuman($event) . "\n";
        }

        if (!$any && $format !== 'json') {
            echo "No events since the last successful event (or no events recorded).\n";
        }

        return 0;
    }
}
