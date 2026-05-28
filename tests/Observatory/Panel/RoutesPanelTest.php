<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Panel;

use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Observatory\Panel\PanelStatus;
use Altair\Observatory\Panel\RoutesPanel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoutesPanel::class)]
final class RoutesPanelTest extends TestCase
{
    public function testIdentity(): void
    {
        $panel = new RoutesPanel(new RouteInspector(new RouteCollection()));

        self::assertSame('routes', $panel->id());
        self::assertSame('Routes', $panel->label());
        self::assertSame('map', $panel->icon());
    }

    public function testSnapshotReportsRoutes(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /users', 'App\\Action\\ListUsers');
        $routes->put('POST /users', 'App\\Action\\CreateUser');

        $snapshot = (new RoutesPanel(new RouteInspector($routes)))->snapshot();

        self::assertSame(PanelStatus::Ok, $snapshot->status);
        self::assertSame('2 routes', $snapshot->headline);
        self::assertSame(2, $snapshot->metrics['routes']);
        self::assertCount(2, $snapshot->items);

        $first = $snapshot->items[0];
        self::assertArrayHasKey('method', $first);
        self::assertArrayHasKey('path', $first);
        self::assertArrayHasKey('handler', $first);
        self::assertSame('/users', $first['path']);
    }

    public function testEmptyRoutesDegradeToUnknown(): void
    {
        $snapshot = (new RoutesPanel(new RouteInspector(new RouteCollection())))->snapshot();

        self::assertSame(PanelStatus::Unknown, $snapshot->status);
        self::assertStringContainsString('unavailable', $snapshot->headline);
        self::assertSame([], $snapshot->items);
        self::assertSame(0, $snapshot->metrics['routes']);
    }
}
