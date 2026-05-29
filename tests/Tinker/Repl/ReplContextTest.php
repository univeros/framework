<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Repl;

use Altair\Tinker\Repl\ReplContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReplContext::class)]
class ReplContextTest extends TestCase
{
    public function testWithScopeVariableReturnsACopy(): void
    {
        $base = new ReplContext(historyFile: '/tmp/h', historySize: 100);

        $derived = $base->withScopeVariable('container', 'X');

        $this->assertSame([], $base->scopeVariables);
        $this->assertSame(['container' => 'X'], $derived->scopeVariables);
        $this->assertSame('/tmp/h', $derived->historyFile);
        $this->assertSame(100, $derived->historySize);
    }

    public function testScopeVariableNames(): void
    {
        $context = (new ReplContext())
            ->withScopeVariable('container', 'c')
            ->withScopeVariable('app', 'a');

        $this->assertSame(['container', 'app'], $context->scopeVariableNames());
    }

    public function testDefaultHistoryFile(): void
    {
        $this->assertSame('.altair/tinker_history', (new ReplContext())->historyFile);
    }
}
