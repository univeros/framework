<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Cli;

use Altair\Suggest\Cli\SuggestCommand;
use Altair\Suggest\Output\RendererRegistry;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\RuleRegistry;
use Altair\Suggest\Snapshot\SnapshotFactory;
use Altair\Suggest\SuggestionEngine;
use Altair\Tests\Suggest\Support\FakeRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SuggestCommand::class)]
class SuggestCommandTest extends TestCase
{
    public function testJsonOutputAndExitCodeReflectWarnings(): void
    {
        $command = $this->command([
            new FakeRule('dead_event', [new Suggestion('dead_event', Severity::Warning, 'x', 'no listeners')]),
        ]);

        ob_start();
        $exit = $command(format: 'json');
        $output = (string) ob_get_clean();

        $this->assertSame(1, $exit);
        $this->assertJson(trim($output));
        $this->assertStringContainsString('"severity": "warning"', $output);
    }

    public function testHumanOutputWithNoSuggestionsExitsZero(): void
    {
        $command = $this->command([new FakeRule('noop')]);

        ob_start();
        $exit = $command(format: 'human');
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('No suggestions', $output);
    }

    public function testSeverityFloorHidesInfoAndDropsExitToZero(): void
    {
        $command = $this->command([
            new FakeRule('a', [new Suggestion('a', Severity::Info, 'x', 'advisory')]),
        ]);

        ob_start();
        $exit = $command(format: 'json', severity: 'warning');
        $output = (string) ob_get_clean();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('"count": 0', $output);
    }

    public function testUnknownFormatExitsTwo(): void
    {
        $command = $this->command([new FakeRule('a')]);

        ob_start();
        $exit = $command(format: 'xml');
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString("Unknown output format 'xml'", $output);
    }

    public function testUnknownSeverityExitsTwo(): void
    {
        $command = $this->command([new FakeRule('a')]);

        ob_start();
        $exit = $command(severity: 'critical');
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString("Unknown severity 'critical'", $output);
    }

    public function testOnlyFlagIsForwarded(): void
    {
        $command = $this->command([
            new FakeRule('dead_event', [new Suggestion('dead_event', Severity::Warning, 'x', 'm')]),
            new FakeRule('other', [new Suggestion('other', Severity::Warning, 'y', 'm')]),
        ]);

        ob_start();
        $exit = $command(format: 'json', only: 'other');
        $output = (string) ob_get_clean();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('"rule": "other"', $output);
        $this->assertStringNotContainsString('dead_event', $output);
    }

    /**
     * @param list<FakeRule> $rules
     */
    private function command(array $rules): SuggestCommand
    {
        return new SuggestCommand(
            new SnapshotFactory(),
            new SuggestionEngine(new RuleRegistry($rules)),
            RendererRegistry::default(),
        );
    }
}
