<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\MigrationIntelligence\Diff\SchemaDiffer;
use Altair\MigrationIntelligence\Emitter\CycleMigrationEmitter;
use Altair\MigrationIntelligence\Output\RendererRegistry;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Planner\PlannerRegistry;
use Altair\MigrationIntelligence\Reader\DbSchemaReader;
use Altair\MigrationIntelligence\Reader\EntitySchemaReader;
use Altair\MigrationIntelligence\Reader\SpecSchemaReader;
use Override;

/**
 * Wires the migration-intelligence services as shared singletons.
 *
 * Every piece is stateless, so a host that applies this gets the same planner,
 * differ, emitter, and renderers behind `db:migration-plan`. The database is
 * read on demand from `DB_*` env by {@see \Altair\MigrationIntelligence\Db\DatabaseProbe},
 * so no connection is established at boot.
 */
final readonly class MigrationIntelligenceConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container
            ->share(SchemaDiffer::class)
            ->share(PlannerRegistry::class)
            ->share(SpecSchemaReader::class)
            ->share(EntitySchemaReader::class)
            ->share(DbSchemaReader::class)
            ->share(CycleMigrationEmitter::class)
            ->share(PlanBuilder::class)

            ->delegate(RendererRegistry::class, static fn(): RendererRegistry => RendererRegistry::default())
            ->share(RendererRegistry::class);
    }
}
