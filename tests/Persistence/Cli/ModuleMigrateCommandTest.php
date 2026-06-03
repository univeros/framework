<?php

declare(strict_types=1);

namespace Altair\Tests\Persistence\Cli;

use Altair\Container\Container;
use Altair\Module\Contracts\MigrationDirectoriesProviderInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\Migration\MigrationSource;
use Altair\Module\ModuleConfiguration;
use Altair\Persistence\Cli\MigrateCommand;
use Altair\Persistence\Cli\MigrateRollbackCommand;
use Altair\Persistence\Cli\MigrateStatusCommand;
use Altair\Persistence\Configuration\DatabaseConnectionFactory;
use Altair\Persistence\Configuration\DatabaseSettings;
use Cycle\Database\DatabaseManager;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Proves a module's migrations are picked up by `db:migrate` with no host
 * wiring beyond registering the module — they run through the SAME migrator as
 * the host's `database/migrations` (via Cycle `vendorDirectories`), sharing the
 * tracking table, with correct status and rollback.
 */
final class ModuleMigrateCommandTest extends TestCase
{
    private DatabaseManager $databases;

    private string $root;

    private string $moduleDir;

    private Container $container;

    #[Override]
    protected function setUp(): void
    {
        $this->databases = (new DatabaseConnectionFactory())->create(new DatabaseSettings(
            driver: DatabaseSettings::DRIVER_SQLITE,
            database: ':memory:',
        ));

        $base = sys_get_temp_dir() . '/altair-module-migrate-' . bin2hex(random_bytes(6));
        $this->root = $base . '/host';
        $this->moduleDir = $base . '/module/database/migrations';
        mkdir($this->root . '/database/migrations', 0o775, true);
        mkdir($this->moduleDir, 0o775, true);

        // A real Cycle migration shipped by the module — Cycle reads its FQCN
        // straight from the file, so the module owns its own namespace.
        file_put_contents(
            $this->moduleDir . '/20260101.000000_0_create_acme_users.php',
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace Acme\UserManagement\Database\Migrations;

                use Cycle\Migrations\Migration;

                final class M20260101000000CreateAcmeUsersTable extends Migration
                {
                    protected const string DATABASE = 'default';

                    public function up(): void
                    {
                        $this->table('acme_users')
                            ->addColumn('id', 'primary', ['nullable' => false])
                            ->addColumn('email', 'string', ['nullable' => false])
                            ->create();
                    }

                    public function down(): void
                    {
                        $this->table('acme_users')->drop();
                    }
                }
                PHP
        );

        $this->container = new Container();
        (new ModuleConfiguration([new FixtureMigratingModule($this->moduleDir)]))->apply($this->container);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Best-effort temp cleanup.
        foreach (glob($this->moduleDir . '/*.php') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function testMigrateAppliesModuleMigration(): void
    {
        $exit = $this->migrate(new MigrateCommand($this->databases, container: $this->container));

        self::assertSame(0, $exit);
        self::assertTrue($this->databases->database('default')->hasTable('acme_users'));
    }

    public function testStatusReportsAppliedAfterMigrate(): void
    {
        $this->migrate(new MigrateCommand($this->databases, container: $this->container));

        ob_start();
        $exit = (new MigrateStatusCommand($this->databases, container: $this->container))(root: $this->root);
        $output = (string) ob_get_clean();

        self::assertSame(0, $exit, 'no pending migrations expected');
        self::assertStringContainsString('applied', $output);
        self::assertStringContainsString('create_acme_users', $output);
    }

    public function testRollbackRemovesModuleMigration(): void
    {
        $this->migrate(new MigrateCommand($this->databases, container: $this->container));
        self::assertTrue($this->databases->database('default')->hasTable('acme_users'));

        ob_start();
        (new MigrateRollbackCommand($this->databases, container: $this->container))(root: $this->root, steps: 1);
        ob_get_clean();

        self::assertFalse($this->databases->database('default')->hasTable('acme_users'));
    }

    private function migrate(MigrateCommand $command): int
    {
        ob_start();
        $exit = $command(root: $this->root);
        ob_get_clean();

        return $exit;
    }
}

final readonly class FixtureMigratingModule implements ModuleInterface, MigrationDirectoriesProviderInterface
{
    public function __construct(private string $directory) {}

    #[Override]
    public function name(): string
    {
        return 'fixture-migrating';
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function migrationDirectories(): array
    {
        return [new MigrationSource($this->directory, 'Acme\\UserManagement\\Database\\Migrations')];
    }
}
