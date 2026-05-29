<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Configuration;

use Altair\Container\Container;
use Altair\Tinker\Configuration\TinkerConfiguration;
use Altair\Tinker\Contracts\ReplInterface;
use Altair\Tinker\Preamble\PreambleBuilder;
use Altair\Tinker\Repl\PsyShellRepl;
use Altair\Tinker\Repl\ReplContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TinkerConfiguration::class)]
class TinkerConfigurationTest extends TestCase
{
    public function testCapturesRealContainerIntoScope(): void
    {
        $container = new Container();
        (new TinkerConfiguration())->apply($container);

        $context = $container->make(ReplContext::class);

        $this->assertInstanceOf(ReplContext::class, $context);
        $this->assertSame($container, $context->scopeVariables['container']);
    }

    public function testWiresPreambleAndRepl(): void
    {
        $container = new Container();
        (new TinkerConfiguration())->apply($container);

        $this->assertInstanceOf(PreambleBuilder::class, $container->make(PreambleBuilder::class));
        $this->assertInstanceOf(PsyShellRepl::class, $container->make(ReplInterface::class));
    }

    public function testHistorySettingsFromConstructorOverrideEnv(): void
    {
        $container = new Container();
        (new TinkerConfiguration(historyFile: '/tmp/explicit_history', historySize: 999))->apply($container);

        $context = $container->make(ReplContext::class);

        $this->assertSame('/tmp/explicit_history', $context->historyFile);
        $this->assertSame(999, $context->historySize);
    }
}
