<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Http\Collection\RouteCollection;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\RouteInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteInspector::class)]
class RouteInspectorTest extends TestCase
{
    public function testInspectAllProducesSortedRows(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /users', 'App\\Action\\ListUsers');
        $routes->put('POST /users', 'App\\Action\\CreateUser');
        $routes->put('GET /', 'App\\Action\\Home');

        $table = (new RouteInspector($routes))->inspectAll();

        $paths = array_column($table->rows, 'path');
        $this->assertSame(['/', '/users', '/users'], $paths);
    }

    public function testInspectOneReturnsAllMethodsForPath(): void
    {
        $routes = new RouteCollection();
        $routes->put('GET /users', 'App\\Action\\ListUsers');
        $routes->put('POST /users', 'App\\Action\\CreateUser');

        $table = (new RouteInspector($routes))->inspectOne('/users');

        $methods = array_column($table->rows, 'method');
        sort($methods);
        $this->assertSame(['GET', 'POST'], $methods);
    }

    public function testInspectOneThrowsOnUnknownPath(): void
    {
        $this->expectException(NotFoundException::class);
        (new RouteInspector(new RouteCollection()))->inspectOne('/nope');
    }
}
