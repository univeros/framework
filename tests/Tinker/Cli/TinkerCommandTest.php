<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Cli;

use Altair\Container\Container;
use Altair\Tinker\Cli\TinkerCommand;
use Altair\Tinker\Preamble\PreambleBuilder;
use Altair\Tinker\Repl\ReplContext;
use Altair\Tests\Tinker\Support\FakeRepl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TinkerCommand::class)]
class TinkerCommandTest extends TestCase
{
    public function testRunsReplWithPreambleAndScope(): void
    {
        $container = new Container();
        $repl = new FakeRepl(exitCode: 0);
        $context = (new ReplContext(historyFile: null))->withScopeVariable('container', $container);
        $command = new TinkerCommand($repl, new PreambleBuilder(), $context);

        $exit = $command();

        $this->assertSame(0, $exit);
        $this->assertNotNull($repl->ranContext);
        $this->assertArrayHasKey('container', $repl->ranContext->scopeVariables);
        $this->assertStringContainsString('Altair Tinker', (string) $repl->ranBanner);
    }

    public function testReturnsTwoWhenPsyshUnavailable(): void
    {
        $command = new TinkerCommand(new FakeRepl(available: false));

        ob_start();
        $exit = $command();
        $output = (string) ob_get_clean();

        $this->assertSame(2, $exit);
        $this->assertStringContainsString('PsySH is not installed', $output);
    }

    public function testHistoryFileOptionOverridesContext(): void
    {
        $repl = new FakeRepl();
        $context = (new ReplContext())->withScopeVariable('container', new Container());
        $command = new TinkerCommand($repl, new PreambleBuilder(), $context);

        $command(historyFile: '/tmp/custom_history');

        $this->assertNotNull($repl->ranContext);
        $this->assertSame('/tmp/custom_history', $repl->ranContext->historyFile);
    }

    public function testDefaultsToFreshContainerInScope(): void
    {
        $repl = new FakeRepl();
        $command = new TinkerCommand($repl, new PreambleBuilder());

        $command();

        $this->assertNotNull($repl->ranContext);
        $this->assertArrayHasKey('container', $repl->ranContext->scopeVariables);
        $this->assertInstanceOf(Container::class, $repl->ranContext->scopeVariables['container']);
    }
}
