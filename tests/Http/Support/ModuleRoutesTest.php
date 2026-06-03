<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use App\Http\Actions\PingAction;
use Altair\Container\Container;
use Altair\Http\Support\ModuleRoutes;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\Contracts\RoutesProviderInterface;
use Altair\Module\ModuleConfiguration;
use Override;
use PHPUnit\Framework\TestCase;

final class ModuleRoutesTest extends TestCase
{
    public function testReturnsBaseRoutesWhenNoModules(): void
    {
        $container = new Container();
        $base = [['GET', '/ping', PingAction::class]];

        self::assertSame($base, ModuleRoutes::collect($container, $base));
    }

    public function testMergesRoutesFromRouteProvidingModules(): void
    {
        $container = new Container();
        (new ModuleConfiguration([
            new RoutingModule(),
            new ServiceOnlyModule(),
        ]))->apply($container);

        $base = [['GET', '/ping', PingAction::class]];

        $expected = [
            ['GET', '/ping', PingAction::class],
            ['GET', '/users', 'Acme\\Http\\ListUsers'],
            ['POST', '/users', 'Acme\\Http\\CreateUser'],
        ];

        self::assertSame($expected, ModuleRoutes::collect($container, $base));
    }

    public function testServiceOnlyModuleContributesNoRoutes(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new ServiceOnlyModule()]))->apply($container);

        self::assertSame([], ModuleRoutes::collect($container, []));
    }
}

final class RoutingModule implements ModuleInterface, RoutesProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'routing';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function routes(): array
    {
        return [
            ['GET', '/users', 'Acme\\Http\\ListUsers'],
            ['POST', '/users', 'Acme\\Http\\CreateUser'],
        ];
    }
}

final class ServiceOnlyModule implements ModuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'service-only';
    }

    #[Override]
    public function apply(Container $container): void {}
}
