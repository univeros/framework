<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Reader;
use DateTimeImmutable;
use Throwable;

/**
 * `bin/altair events:since <when>` — events strictly after a timestamp or
 * event-id, newest first. The argument is auto-detected: ULID-shaped
 * strings are treated as event ids, anything else as a date/time spec
 * understood by `DateTimeImmutable::__construct()`.
 */
#[Command(
    name: 'events:since',
    description: 'Print events recorded after a timestamp or event id.',
)]
final readonly class SinceCommand
{
    private const string ULID_PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    public function __construct(
        private Reader $reader,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Argument(description: 'A timestamp (e.g. 2026-05-27T10:00:00Z) or an event id (ULID).')]
        string $when,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        if (preg_match(self::ULID_PATTERN, $when) === 1) {
            $events = $this->reader->sinceId($when);
        } else {
            try {
                $threshold = new DateTimeImmutable($when);
            } catch (Throwable $e) {
                echo \sprintf("Could not parse '%s' as a timestamp: %s%s", $when, $e->getMessage(), PHP_EOL);

                return 2;
            }

            $events = $this->reader->since($threshold);
        }

        $any = false;
        foreach ($events as $event) {
            $any = true;
            echo $format === 'json'
                ? $this->renderer->eventJson($event) . "\n"
                : $this->renderer->eventLineHuman($event) . "\n";
        }

        if (!$any && $format !== 'json') {
            echo "No events after '{$when}'.\n";
        }

        return 0;
    }
}
