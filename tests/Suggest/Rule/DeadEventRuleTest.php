<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Rule;

use Altair\Suggest\Result\Severity;
use Altair\Suggest\Rule\DeadEventRule;
use Altair\Suggest\Snapshot\EventNode;
use Altair\Suggest\Snapshot\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeadEventRule::class)]
class DeadEventRuleTest extends TestCase
{
    public function testFlagsEventWithNoListenersAsWarning(): void
    {
        $snapshot = new Snapshot(events: [
            new EventNode('order.placed', 0),
            new EventNode('user.created', 2),
        ]);

        $suggestions = (new DeadEventRule())->analyse($snapshot);

        $this->assertCount(1, $suggestions);
        $this->assertSame('order.placed', $suggestions[0]->subject);
        $this->assertSame(Severity::Warning, $suggestions[0]->severity);
        $this->assertSame('dead_event', $suggestions[0]->rule);
    }

    public function testNoSuggestionsWhenAllEventsHaveListeners(): void
    {
        $snapshot = new Snapshot(events: [new EventNode('user.created', 1)]);

        $this->assertSame([], (new DeadEventRule())->analyse($snapshot));
    }
}
