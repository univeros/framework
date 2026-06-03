<?php

declare(strict_types=1);

namespace VendorModule;

use Altair\Container\Container;
use Altair\Module\Contracts\EntityDirectoriesProviderInterface;
use Altair\Module\Contracts\MigrationDirectoriesProviderInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\Contracts\RoutesProviderInterface;
use Altair\Module\Migration\MigrationSource;
use Override;
use VendorModule\Domain\SampleService;
use VendorModule\Http\Actions\SampleAction;

/**
 * The module entry point. A host app registers this one class in
 * `config/modules.php` and gets everything below wired automatically:
 *
 *   - service bindings (here in {@see self::apply()})
 *   - HTTP routes        ({@see self::routes()})
 *   - Cycle entities     ({@see self::entityDirectories()})
 *   - migrations         ({@see self::migrationDirectories()})
 *
 * Drop any capability you don't need by removing its interface from the
 * `implements` list — a service-only module need only implement ModuleInterface.
 */
final class Module implements
    ModuleInterface,
    RoutesProviderInterface,
    EntityDirectoriesProviderInterface,
    MigrationDirectoriesProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'vendor/module';
    }

    /**
     * Register the module's own services. The container auto-wires concrete
     * classes, so you only bind interfaces, factories and shared singletons.
     */
    #[Override]
    public function apply(Container $container): void
    {
        $container->singleton(SampleService::class);
    }

    /**
     * @return list<array{0: string, 1: string, 2: class-string}>
     */
    #[Override]
    public function routes(): array
    {
        return [
            ['GET', '/sample', SampleAction::class],
        ];
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function entityDirectories(): array
    {
        return [__DIR__ . '/Entity'];
    }

    /**
     * @return list<MigrationSource>
     */
    #[Override]
    public function migrationDirectories(): array
    {
        // __NAMESPACE__ resolves to this module's namespace, so the migration
        // namespace stays correct after scaffolding renames the package.
        return [
            new MigrationSource(
                \dirname(__DIR__) . '/database/migrations',
                __NAMESPACE__ . '\\Database\\Migrations',
            ),
        ];
    }
}
