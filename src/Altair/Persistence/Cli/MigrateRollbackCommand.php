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
use Altair\Persistence\Migrations\MigrationConfigFactory;
use Altair\Persistence\Migrations\MigratorFactory;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\MigrationInterface;

/**
 * `bin/altair db:migrate:rollback` — roll back the most recent migration.
 *
 * Pass `--steps=N` to roll back the last N migrations.
 */
#[Command(
    name: 'db:migrate:rollback',
    description: 'Roll back the most recently applied Cycle ORM migration(s).',
)]
final readonly class MigrateRollbackCommand
{
    public function __construct(
        private DatabaseProviderInterface $databases,
        private MigrationConfigFactory $configs = new MigrationConfigFactory(),
        private MigratorFactory $migrators = new MigratorFactory(),
        private MigrationPathResolver $paths = new MigrationPathResolver(),
    ) {}

    public function __invoke(
        #[Option(description: 'Override the project root used to locate migration files.')]
        ?string $root = null,
        #[Option(description: 'Override the migrations directory (relative to project root).', name: 'dir')]
        ?string $dir = null,
        #[Option(description: 'Number of migrations to roll back.', name: 'steps')]
        int $steps = 1,
    ): int {
        if ($steps < 1) {
            echo "--steps must be >= 1.\n";

            return 2;
        }

        $projectRoot = $this->paths->resolveProjectRoot($root);
        $directory = $this->paths->resolveMigrationsDirectory($projectRoot, $dir);

        $config = $this->configs->create($directory);
        $migrator = $this->migrators->create($this->databases, $config);

        for ($i = 0; $i < $steps; $i++) {
            $rolledBack = $migrator->rollback();
            if (!$rolledBack instanceof MigrationInterface) {
                echo "Nothing to roll back.\n";
                break;
            }

            echo '  rolled back ' . $rolledBack->getState()->getName() . "\n";
        }

        return 0;
    }
}
