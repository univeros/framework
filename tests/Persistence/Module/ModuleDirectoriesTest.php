<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Module;

use Altair\Container\Container;
use Altair\Module\Contracts\EntityDirectoriesProviderInterface;
use Altair\Module\Contracts\MigrationDirectoriesProviderInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\Migration\MigrationSource;
use Altair\Module\ModuleConfiguration;
use Altair\Persistence\Schema\ModuleEntityDirectories;
use Altair\Persistence\Migrations\ModuleMigrationDirectories;
use Override;
use PHPUnit\Framework\TestCase;

final class ModuleDirectoriesTest extends TestCase
{
    public function testEntityDirectoriesMergeBaseFirstThenModules(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new PersistedModule(), new PlainModule()]))->apply($container);

        $result = ModuleEntityDirectories::collect($container, ['/app/Entity']);

        self::assertSame(['/app/Entity', '/acme/src/Entity'], $result);
    }

    public function testEntityDirectoriesWithNoModulesReturnsBase(): void
    {
        $container = new Container();

        self::assertSame(['/app/Entity'], ModuleEntityDirectories::collect($container, ['/app/Entity']));
    }

    public function testMigrationDirectoriesFlattenModuleSources(): void
    {
        $container = new Container();
        (new ModuleConfiguration([new PersistedModule(), new PlainModule()]))->apply($container);

        self::assertSame(['/acme/database/migrations'], ModuleMigrationDirectories::collect($container));
    }

    public function testMigrationDirectoriesWithNoModulesIsEmpty(): void
    {
        self::assertSame([], ModuleMigrationDirectories::collect(new Container()));
    }
}

final class PersistedModule implements ModuleInterface, EntityDirectoriesProviderInterface, MigrationDirectoriesProviderInterface
{
    #[Override]
    public function name(): string
    {
        return 'persisted';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function entityDirectories(): array
    {
        return ['/acme/src/Entity'];
    }

    #[Override]
    public function migrationDirectories(): array
    {
        return [new MigrationSource('/acme/database/migrations', 'Acme\\Database\\Migrations')];
    }
}

final class PlainModule implements ModuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'plain';
    }

    #[Override]
    public function apply(Container $container): void {}
}
