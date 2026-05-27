<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Migrations;

use Cycle\Database\DatabaseProviderInterface;
use Cycle\Migrations\Config\MigrationConfig;
use Cycle\Migrations\FileRepository;
use Cycle\Migrations\Migrator;

/**
 * Builds a configured Cycle {@see Migrator} ready to run.
 *
 * Wraps the boilerplate of constructing the file repository so callers
 * (CLI commands, tests) can stay declarative.
 */
final readonly class MigratorFactory
{
    public function create(DatabaseProviderInterface $databases, MigrationConfig $config): Migrator
    {
        $migrator = new Migrator($config, $databases, new FileRepository($config));
        $migrator->configure();

        return $migrator;
    }
}
