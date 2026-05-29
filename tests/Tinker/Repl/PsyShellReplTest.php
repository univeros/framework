<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Repl;

use Altair\Container\Container;
use Altair\Tinker\Repl\PsyShellRepl;
use Altair\Tinker\Repl\ReplContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psy\Shell;

#[CoversClass(PsyShellRepl::class)]
class PsyShellReplTest extends TestCase
{
    public function testBuildPutsScopeVariablesIntoTheShell(): void
    {
        $container = new Container();
        $context = (new ReplContext(historyFile: null))->withScopeVariable('container', $container);

        $shell = (new PsyShellRepl())->build($context, 'banner');

        $this->assertInstanceOf(Shell::class, $shell);
        $scope = $shell->getScopeVariables();
        $this->assertArrayHasKey('container', $scope);
        $this->assertSame($container, $scope['container']);
    }
}
