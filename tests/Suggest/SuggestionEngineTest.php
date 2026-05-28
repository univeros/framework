<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest;

use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\RuleRegistry;
use Altair\Suggest\Snapshot\Snapshot;
use Altair\Suggest\SuggestionEngine;
use Altair\Tests\Suggest\Support\FakeRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestionEngine::class)]
#[CoversClass(RuleRegistry::class)]
class SuggestionEngineTest extends TestCase
{
    public function testAggregatesSuggestionsFromEveryRule(): void
    {
        $engine = $this->engine([
            new FakeRule('a', [new Suggestion('a', Severity::Info, 's', 'm')]),
            new FakeRule('b', [new Suggestion('b', Severity::Warning, 's', 'm')]),
        ]);

        $report = $engine->analyse(new Snapshot());

        $this->assertCount(2, $report->suggestions);
    }

    public function testSeverityFloorDropsLowerSeverity(): void
    {
        $engine = $this->engine([
            new FakeRule('a', [new Suggestion('a', Severity::Info, 's', 'm')]),
            new FakeRule('b', [new Suggestion('b', Severity::Warning, 's', 'm')]),
        ]);

        $report = $engine->analyse(new Snapshot(), Severity::Warning);

        $this->assertCount(1, $report->suggestions);
        $this->assertSame(Severity::Warning, $report->suggestions[0]->severity);
    }

    public function testOnlyRunsNamedRulesExclusively(): void
    {
        $a = new FakeRule('a', [new Suggestion('a', Severity::Info, 's', 'm')]);
        $b = new FakeRule('b', [new Suggestion('b', Severity::Info, 's', 'm')]);

        $report = $this->engine([$a, $b])->analyse(new Snapshot(), Severity::Info, ['b']);

        $this->assertFalse($a->analysed);
        $this->assertTrue($b->analysed);
        $this->assertCount(1, $report->suggestions);
    }

    public function testSkipExcludesNamedRules(): void
    {
        $a = new FakeRule('a', [new Suggestion('a', Severity::Info, 's', 'm')]);

        $report = $this->engine([$a])->analyse(new Snapshot(), Severity::Info, [], ['a']);

        $this->assertFalse($a->analysed);
        $this->assertSame([], $report->suggestions);
    }

    public function testDeterministicOrderWarningsFirstThenRuleThenSubject(): void
    {
        $engine = $this->engine([
            new FakeRule('zeta', [new Suggestion('zeta', Severity::Info, 'b', 'm')]),
            new FakeRule('alpha', [
                new Suggestion('alpha', Severity::Info, 'b', 'm'),
                new Suggestion('alpha', Severity::Info, 'a', 'm'),
            ]),
            new FakeRule('beta', [new Suggestion('beta', Severity::Warning, 'z', 'm')]),
        ]);

        $report = $engine->analyse(new Snapshot());

        $order = array_map(static fn(Suggestion $s): string => $s->rule . ':' . $s->subject, $report->suggestions);
        $this->assertSame(['beta:z', 'alpha:a', 'alpha:b', 'zeta:b'], $order);
    }

    /**
     * @param list<FakeRule> $rules
     */
    private function engine(array $rules): SuggestionEngine
    {
        return new SuggestionEngine(new RuleRegistry($rules));
    }
}
