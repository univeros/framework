<?php

declare(strict_types=1);

namespace Altair\Tests\Suggest\Rule;

use Altair\Suggest\Result\Severity;
use Altair\Suggest\Rule\RouteWithoutSpecRule;
use Altair\Suggest\Snapshot\RouteNode;
use Altair\Suggest\Snapshot\Snapshot;
use Altair\Suggest\Snapshot\SpecNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteWithoutSpecRule::class)]
class RouteWithoutSpecRuleTest extends TestCase
{
    public function testFlagsRouteWithNoMatchingSpec(): void
    {
        $snapshot = new Snapshot(
            routes: [
                new RouteNode('GET', '/users', 'App\\Action\\ListUsers'),
                new RouteNode('DELETE', '/users/{id}', 'App\\Action\\DeleteUser'),
            ],
            specs: [new SpecNode('users/list.yaml', 'GET', '/users')],
        );

        $suggestions = (new RouteWithoutSpecRule())->analyse($snapshot);

        $this->assertCount(1, $suggestions);
        $this->assertSame('DELETE /users/{id}', $suggestions[0]->subject);
        $this->assertSame(Severity::Info, $suggestions[0]->severity);
    }

    public function testMethodMatchIsCaseInsensitiveAndPathTrailingSlashTolerant(): void
    {
        $snapshot = new Snapshot(
            routes: [new RouteNode('GET', '/users/', 'A')],
            specs: [new SpecNode('s.yaml', 'get', '/users')],
        );

        $this->assertSame([], (new RouteWithoutSpecRule())->analyse($snapshot));
    }

    public function testSilentWhenNoSpecsCollected(): void
    {
        $snapshot = new Snapshot(routes: [new RouteNode('GET', '/users', 'A')]);

        $this->assertSame([], (new RouteWithoutSpecRule())->analyse($snapshot));
    }

    public function testSilentWhenNoRoutes(): void
    {
        $snapshot = new Snapshot(specs: [new SpecNode('s.yaml', 'GET', '/users')]);

        $this->assertSame([], (new RouteWithoutSpecRule())->analyse($snapshot));
    }
}
