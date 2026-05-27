<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Cli;

use Altair\Events\Changes;
use Altair\Events\Event;
use DateTimeInterface;

/**
 * Shared rendering for `bin/altair events:*` commands.
 *
 * - JSON modes always emit one JSON object per line (NDJSON-friendly for
 *   `jq` and MCP consumers), or one array for collection endpoints.
 * - Human modes are pipe-readable: one event per line, short fields.
 */
final readonly class OutputRenderer
{
    public function eventLineHuman(Event $event): string
    {
        $changes = $event->changes instanceof Changes
            ? $this->summariseChanges($event->changes->toArray())
            : '';

        return \sprintf(
            "%s  %-8s  %-19s  %-3s  %5dms  %s%s",
            substr($event->id, 0, 10),
            substr($event->kind->value, 0, 8),
            $event->timestamp->format('Y-m-d H:i:s'),
            $event->status->value,
            $event->durationMs,
            $event->command,
            $changes === '' ? '' : \sprintf('  [%s]', $changes),
        );
    }

    public function eventJson(Event $event): string
    {
        return json_encode(
            $event->toArray(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    public function eventDetailHuman(Event $event): string
    {
        $out = [];
        $out[] = 'id:         ' . $event->id;
        $out[] = 'timestamp:  ' . $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED);
        $out[] = 'actor:      ' . $event->actor->value;
        if ($event->user !== null) {
            $out[] = 'user:       ' . $event->user;
        }

        if ($event->client !== null) {
            $out[] = 'client:     ' . $event->client;
        }

        $out[] = 'command:    ' . $event->command;
        $out[] = 'kind:       ' . $event->kind->value;
        $out[] = 'status:     ' . $event->status->value;
        $out[] = 'duration:   ' . $event->durationMs . 'ms';
        if ($event->error !== null) {
            $out[] = 'error:      ' . $event->error;
        }

        if ($event->changes instanceof Changes) {
            $out[] = 'changes:';
            foreach ($event->changes->toArray() as $bucket => $entries) {
                if ($bucket === 'snapshot_ref') {
                    $out[] = '  snapshot_ref: ' . $entries;
                    continue;
                }

                $out[] = '  ' . $bucket . ':';
                if (\is_array($entries)) {
                    foreach ($entries as $entry) {
                        $out[] = '    - ' . $entry;
                    }
                }
            }
        }

        if ($event->extra !== []) {
            $out[] = 'extra:      ' . json_encode($event->extra, JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $out) . "\n";
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function summariseChanges(array $changes): string
    {
        $parts = [];
        foreach ($changes as $verb => $entries) {
            if ($verb === 'snapshot_ref') {
                continue;
            }

            if (\is_array($entries) && $entries !== []) {
                $parts[] = $verb . ':' . \count($entries);
            }
        }

        return implode(',', $parts);
    }
}
