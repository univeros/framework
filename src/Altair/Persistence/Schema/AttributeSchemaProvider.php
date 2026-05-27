<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Schema;

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
use Cycle\Schema\Registry;
use Override;
use Spiral\Tokenizer\Config\TokenizerConfig;
use Spiral\Tokenizer\Tokenizer;

/**
 * Discovers Cycle-annotated entity classes from a list of source directories
 * and compiles them into a runtime schema array.
 *
 * The compiled schema is memoized for the lifetime of the provider instance,
 * so the heavy reflection / parsing work runs at most once per process.
 *
 * @phpstan-import-type CycleSchema from SchemaProviderInterface
 */
final class AttributeSchemaProvider implements SchemaProviderInterface
{
    /** @var CycleSchema|null */
    private ?array $compiled = null;

    /**
     * @param list<string> $entityDirectories Absolute paths to scan for entity classes.
     */
    public function __construct(
        private readonly DatabaseProviderInterface $databases,
        private readonly array $entityDirectories,
    ) {}

    #[Override]
    public function schema(): array
    {
        return $this->compiled ??= $this->compile();
    }

    /**
     * @return CycleSchema
     */
    private function compile(): array
    {
        $classes = (new Tokenizer(new TokenizerConfig([
            'directories' => $this->entityDirectories,
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

        return (new Compiler())->compile($registry, $generators);
    }
}
