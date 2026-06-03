<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Schema;

use Altair\Container\Container;
use Altair\Module\Contracts\EntityDirectoriesProviderInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\ModuleConfiguration;
use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Altair\Persistence\Schema\ModuleAwareSchemaProvider;
use Cycle\Database\DatabaseProviderInterface;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Proves a module's Cycle entities are compiled into the runtime schema with no
 * host wiring beyond registering the module.
 */
final class ModuleAwareSchemaProviderTest extends TestCase
{
    private DatabaseProviderInterface $databases;

    #[Override]
    protected function setUp(): void
    {
        $this->databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));
    }

    public function testModuleEntitiesAreCompiledIntoSchema(): void
    {
        $container = new Container();
        $entityDir = __DIR__ . '/Fixture/ModuleEntities';
        (new ModuleConfiguration([new EntityShippingModule($entityDir)]))->apply($container);

        $provider = new ModuleAwareSchemaProvider($this->databases, $container, baseDirectories: []);

        // The module's table appears in the compiled schema, proving its entity
        // directory was scanned without any host-side configuration.
        self::assertStringContainsString('module_accounts', json_encode($provider->schema(), JSON_THROW_ON_ERROR));
    }
}

final readonly class EntityShippingModule implements ModuleInterface, EntityDirectoriesProviderInterface
{
    public function __construct(private string $directory) {}

    #[Override]
    public function name(): string
    {
        return 'entity-shipping';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function entityDirectories(): array
    {
        return [$this->directory];
    }
}
