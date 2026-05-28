<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Rule;

use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\Snapshot;
use Override;

/**
 * An event registered in the dispatcher with zero listeners is dispatched to
 * nobody — dead weight. This is the highest-confidence finding the analyser
 * makes (the dispatcher map literally holds the key with an empty listener
 * list), so it is a `warning` rather than advisory `info`.
 */
final readonly class DeadEventRule implements SuggestionRuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'dead_event';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        $out = [];
        foreach ($snapshot->events as $event) {
            if ($event->listeners === 0) {
                $out[] = new Suggestion(
                    rule: $this->name(),
                    severity: Severity::Warning,
                    subject: $event->event,
                    message: \sprintf("Event '%s' is registered but has no listeners — it is dispatched to nobody.", $event->event),
                    fix: 'Register a listener or remove the event registration.',
                );
            }
        }

        return $out;
    }
}
