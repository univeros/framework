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
use Cycle\Annotated\Embeddings;
use Cycle\Annotated\Entities;
use Cycle\Annotated\Locator\TokenizerEmbeddingLocator;
use Cycle\Annotated\Locator\TokenizerEntityLocator;
use Cycle\Annotated\MergeColumns;
use Cycle\Annotated\MergeIndexes;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Schema\Compiler;
use Cycle\Schema\Generator\GenerateRelations;
use Cycle\Schema\Generator\GenerateTypecast;
use Cycle\Schema\Generator\RenderRelations;
use Cycle\Schema\Generator\RenderTables;
use Cycle\Schema\Generator\ResetTables;
use Cycle\Schema\Generator\SyncTables;
use Cycle\Schema\Registry;
use Spiral\Tokenizer\Config\TokenizerConfig;
use Spiral\Tokenizer\Tokenizer;

/**
 * `bin/altair db:schema-sync` — DEV-ONLY helper that diffs entity classes
 * against the live database and applies the differences directly, with no
 * migration files involved.
 *
 * Never run this against production; use {@see MigrateCommand} instead.
 */
#[Command(
    name: 'db:schema-sync',
    description: 'Dev-only: diff entity classes against the live DB and apply changes without writing migrations.',
)]
final readonly class SchemaSyncCommand
{
    public function __construct(private DatabaseProviderInterface $databases) {}

    public function __invoke(
        #[Option(description: 'Comma-separated list of absolute directories to scan for entity classes.', name: 'entities')]
        string $entityDirs = '',
        #[Option(description: 'Print diff without applying.', name: 'dry-run')]
        bool $dryRun = false,
    ): int {
        $directories = array_values(array_filter(array_map('trim', explode(',', $entityDirs))));
        if ($directories === []) {
            echo "Provide --entities=/abs/path,/abs/path2 with at least one directory.\n";

            return 2;
        }

        $classes = (new Tokenizer(new TokenizerConfig([
            'directories' => $directories,
            'exclude' => [],
        ])))->classLocator();

        $registry = new Registry($this->databases);

        $generators = [
            new ResetTables(),
            new Embeddings(new TokenizerEmbeddingLocator($classes)),
            new Entities(new TokenizerEntityLocator($classes)),
            new MergeColumns(),
            new GenerateRelations(),
            new RenderTables(),
            new RenderRelations(),
            new MergeIndexes(),
            new GenerateTypecast(),
        ];

        (new Compiler())->compile($registry, $generators);

        if ($dryRun) {
            echo "Dry run — no schema changes will be applied.\n";
            foreach ($registry->getIterator() as $entity) {
                echo '  would sync ' . $entity->getRole() . "\n";
            }

            return 0;
        }

        // SyncTables applies pending DDL directly to the connected databases.
        (new SyncTables())->run($registry);

        echo "Schema synced.\n";

        return 0;
    }
}
