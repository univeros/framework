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
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Events\Reader;
use Throwable;

/**
 * `bin/altair events:filter --kind=… --status=…` — newest-first filtered
 * dump of the log.
 */
#[Command(
    name: 'events:filter',
    description: 'Filter events by kind and/or status.',
)]
final readonly class FilterCommand
{
    public function __construct(
        private Reader $reader,
        private OutputRenderer $renderer = new OutputRenderer(),
    ) {}

    public function __invoke(
        #[Option(description: 'Comma-separated event kinds (e.g. scaffold,migration).')]
        ?string $kind = null,
        #[Option(description: 'Comma-separated event statuses (ok, fail, partial).')]
        ?string $status = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        try {
            $kinds = $this->parseKinds($kind);
            $statuses = $this->parseStatuses($status);
        } catch (Throwable $throwable) {
            echo $throwable->getMessage(), "\n";

            return 2;
        }

        $any = false;
        foreach ($this->reader->filter($kinds, $statuses) as $event) {
            $any = true;
            echo $format === 'json'
                ? $this->renderer->eventJson($event) . "\n"
                : $this->renderer->eventLineHuman($event) . "\n";
        }

        if (!$any && $format !== 'json') {
            echo "No events match the given filter.\n";
        }

        return 0;
    }

    /**
     * @return list<EventKind>
     */
    private function parseKinds(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn(string $p): bool => $p !== '');

        return array_map(EventKind::fromString(...), array_values($parts));
    }

    /**
     * @return list<EventStatus>
     */
    private function parseStatuses(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $raw)), static fn(string $p): bool => $p !== '');

        return array_map(EventStatus::from(...), array_values($parts));
    }
}
