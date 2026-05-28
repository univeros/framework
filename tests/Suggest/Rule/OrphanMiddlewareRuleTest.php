<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Rule;

use Altair\Suggest\Rule\OrphanMiddlewareRule;
use Altair\Suggest\Snapshot\BindingNode;
use Altair\Suggest\Snapshot\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;

#[CoversClass(OrphanMiddlewareRule::class)]
class OrphanMiddlewareRuleTest extends TestCase
{
    public function testFlagsMiddlewareBindingNotInPipeline(): void
    {
        $snapshot = new Snapshot(
            bindings: [
                $this->middleware('App\\Middleware\\Cors'),
                $this->middleware('App\\Middleware\\Auth'),
            ],
            middleware: ['App\\Middleware\\Auth'],
        );

        $suggestions = (new OrphanMiddlewareRule())->analyse($snapshot);

        $this->assertCount(1, $suggestions);
        $this->assertSame('App\\Middleware\\Cors', $suggestions[0]->subject);
        $this->assertSame('orphan_middleware', $suggestions[0]->rule);
    }

    public function testPipelineMatchByConcreteTargetClearsInterfaceBinding(): void
    {
        // Binding id is the interface; the pipeline lists the concrete target.
        $snapshot = new Snapshot(
            bindings: [new BindingNode(
                'App\\Contract\\AuthMiddleware',
                'alias',
                'App\\Middleware\\Auth',
                false,
                [],
                [MiddlewareInterface::class],
            )],
            middleware: ['App\\Middleware\\Auth'],
        );

        $this->assertSame([], (new OrphanMiddlewareRule())->analyse($snapshot));
    }

    public function testIgnoresNonMiddlewareBindings(): void
    {
        $snapshot = new Snapshot(
            bindings: [new BindingNode('App\\Service\\Mailer', 'share', 'App\\Service\\Mailer', true)],
            middleware: ['App\\Middleware\\Auth'],
        );

        $this->assertSame([], (new OrphanMiddlewareRule())->analyse($snapshot));
    }

    public function testSilentWhenNoPipelineInspected(): void
    {
        $snapshot = new Snapshot(bindings: [$this->middleware('App\\Middleware\\Cors')]);

        $this->assertSame([], (new OrphanMiddlewareRule())->analyse($snapshot));
    }

    private function middleware(string $class): BindingNode
    {
        return new BindingNode($class, 'share', $class, true, [], [MiddlewareInterface::class]);
    }
}
