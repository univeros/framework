<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Rule;

use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Rule\DeadBindingRule;
use Altair\Suggest\Snapshot\BindingNode;
use Altair\Suggest\Snapshot\EventNode;
use Altair\Suggest\Snapshot\RouteNode;
use Altair\Suggest\Snapshot\Snapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\MiddlewareInterface;

#[CoversClass(DeadBindingRule::class)]
class DeadBindingRuleTest extends TestCase
{
    public function testFlagsBindingNothingDependsOn(): void
    {
        $snapshot = new Snapshot(bindings: [
            new BindingNode('App\\Used', 'share', 'App\\Used', true),
            new BindingNode('App\\Consumer', 'share', 'App\\Consumer', true, ['App\\Used']),
            new BindingNode('App\\Orphan', 'share', 'App\\Orphan', true),
        ]);

        $suggestions = (new DeadBindingRule())->analyse($snapshot);

        $subjects = array_map(static fn(Suggestion $s): string => $s->subject, $suggestions);
        // Used is depended on by Consumer, so it is referenced; Orphan and the
        // top-level Consumer are both unreferenced roots (the rule's known
        // false-positive surface — hence info severity).
        $this->assertContains('App\\Orphan', $subjects);
        $this->assertNotContains('App\\Used', $subjects);
        $this->assertSame(Severity::Info, $suggestions[0]->severity);
    }

    public function testFlagsUnreferencedDelegateKind(): void
    {
        $snapshot = new Snapshot(bindings: [
            new BindingNode('App\\Factory\\Made', 'delegate', 'App\\Factory\\Made', true),
        ]);

        $subjects = array_map(static fn(Suggestion $s): string => $s->subject, (new DeadBindingRule())->analyse($snapshot));

        $this->assertContains('App\\Factory\\Made', $subjects);
    }

    public function testExemptsMiddlewareEntryPoints(): void
    {
        $snapshot = new Snapshot(bindings: [
            new BindingNode('App\\Mw', 'share', 'App\\Mw', true, [], [MiddlewareInterface::class]),
        ]);

        $this->assertSame([], (new DeadBindingRule())->analyse($snapshot));
    }

    public function testFollowsAliasSoInterfaceDependencyMarksConcreteUsed(): void
    {
        $snapshot = new Snapshot(bindings: [
            new BindingNode('App\\LoggerInterface', 'alias', 'App\\FileLogger', false),
            new BindingNode('App\\FileLogger', 'share', 'App\\FileLogger', true),
            new BindingNode('App\\Consumer', 'share', 'App\\Consumer', true, ['App\\LoggerInterface']),
        ]);

        $subjects = array_map(static fn(Suggestion $s): string => $s->subject, (new DeadBindingRule())->analyse($snapshot));

        $this->assertNotContains('App\\FileLogger', $subjects, 'concrete behind a used interface is not dead');
    }

    public function testRouteActionAndListenerTargetsCountAsReferences(): void
    {
        $snapshot = new Snapshot(
            bindings: [
                new BindingNode('App\\Action\\Home', 'share', 'App\\Action\\Home', true),
                new BindingNode('App\\Listener\\OnSignup', 'share', 'App\\Listener\\OnSignup', true),
            ],
            routes: [new RouteNode('GET', '/', 'App\\Action\\Home::__invoke')],
            events: [new EventNode('user.created', 1, ['App\\Listener\\OnSignup'])],
        );

        $this->assertSame([], (new DeadBindingRule())->analyse($snapshot));
    }

    public function testSkipsAliasAndParameterKinds(): void
    {
        $snapshot = new Snapshot(bindings: [
            new BindingNode('App\\SomeInterface', 'alias', 'App\\Impl', false),
        ]);

        $this->assertSame([], (new DeadBindingRule())->analyse($snapshot));
    }
}
