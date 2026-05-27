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
use Cycle\Migrations\State;

/**
 * `bin/altair db:migrate` — apply every pending migration in order.
 *
 * With `--dry-run` the command lists pending migrations without applying
 * any of them. Per-migration SQL preview is a follow-up; Cycle's runtime
 * does not expose a non-destructive SQL dump out of the box.
 */
#[Command(
    name: 'db:migrate',
    description: 'Apply pending Cycle ORM migrations.',
)]
final readonly class MigrateCommand
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
        #[Option(description: 'List pending migrations without applying them.', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $projectRoot = $this->paths->resolveProjectRoot($root);
        $directory = $this->paths->resolveMigrationsDirectory($projectRoot, $dir);

        $config = $this->configs->create($directory);
        $migrator = $this->migrators->create($this->databases, $config);

        $pending = array_filter(
            $migrator->getMigrations(),
            static fn(object $migration): bool => $migration->getState()->getStatus() === State::STATUS_PENDING,
        );

        if ($pending === []) {
            echo "No pending migrations.\n";

            return 0;
        }

        if ($dryRun) {
            echo "Pending migrations (not applied):\n";
            foreach ($pending as $migration) {
                echo '  ' . $migration->getState()->getName() . "\n";
            }

            return 0;
        }

        foreach ($pending as $_) {
            $applied = $migrator->run();
            if (!$applied instanceof MigrationInterface) {
                break;
            }

            echo '  applied ' . $applied->getState()->getName() . "\n";
        }

        return 0;
    }
}
