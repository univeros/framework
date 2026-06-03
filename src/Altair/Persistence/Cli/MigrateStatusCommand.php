<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Container\Container;
use Altair\Persistence\Migrations\MigrationConfigFactory;
use Altair\Persistence\Migrations\MigratorFactory;
use Altair\Persistence\Migrations\ModuleMigrationDirectories;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\State;

/**
 * `bin/altair db:migrate:status` — list every known migration and whether
 * it has been applied yet.
 *
 * Returns exit code 1 when there is at least one pending migration so CI
 * scripts can fail loud.
 */
#[Command(
    name: 'db:migrate:status',
    description: 'List every Cycle ORM migration and its applied/pending state.',
)]
final readonly class MigrateStatusCommand
{
    public function __construct(
        private DatabaseProviderInterface $databases,
        private MigrationConfigFactory $configs = new MigrationConfigFactory(),
        private MigratorFactory $migrators = new MigratorFactory(),
        private MigrationPathResolver $paths = new MigrationPathResolver(),
        private ?Container $container = null,
    ) {}

    public function __invoke(
        #[Option(description: 'Override the project root used to locate migration files.')]
        ?string $root = null,
        #[Option(description: 'Override the migrations directory (relative to project root).', name: 'dir')]
        ?string $dir = null,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);
        $directory = $this->paths->resolveMigrationsDirectory($projectRoot, $dir);

        $config = $this->configs->create(
            $directory,
            vendorDirectories: ModuleMigrationDirectories::existingFor($this->container),
        );
        $migrator = $this->migrators->create($this->databases, $config);

        $migrations = $migrator->getMigrations();
        if ($migrations === []) {
            echo "No migrations found.\n";

            return 0;
        }

        $hasPending = false;
        foreach ($migrations as $migration) {
            $state = $migration->getState();
            $applied = $state->getStatus() === State::STATUS_EXECUTED;
            $label = $applied ? 'applied ' : 'pending ';
            if (!$applied) {
                $hasPending = true;
            }

            echo $label . $state->getName() . "\n";
        }

        return $hasPending ? 1 : 0;
    }
}
