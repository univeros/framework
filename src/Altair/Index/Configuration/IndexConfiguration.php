<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Index\Builder\IndexConfig;
use Altair\Index\Query\ImpactQuery;
use Altair\Index\Query\OrphanQuery;
use Altair\Index\Query\UsageQuery;
use Altair\Index\Support\ProjectIndex;
use Override;

/**
 * Wires a shared {@see ProjectIndex} and the read queries into the Container.
 *
 * Optional: the `index:*` CLI commands build a {@see ProjectIndex} from the
 * current working directory when none is bound, so this Configuration is only
 * needed by hosts (and the MCP server) that want an explicit project root or to
 * inject the queries elsewhere. The index database defaults to
 * `.altair/index.db` under the root.
 */
final readonly class IndexConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $projectRoot = null,
        private ?string $databasePath = null,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $root = $this->projectRoot ?? (string) getcwd();
        $databasePath = $this->databasePath;

        $container
            ->factory(ProjectIndex::class, static fn(): ProjectIndex => new ProjectIndex(IndexConfig::forRoot($root, $databasePath)))
            ->shared();

        $container->factory(UsageQuery::class, static fn(ProjectIndex $index): UsageQuery => $index->usages())->shared();
        $container->factory(ImpactQuery::class, static fn(ProjectIndex $index): ImpactQuery => $index->impact())->shared();
        $container->factory(OrphanQuery::class, static fn(ProjectIndex $index): OrphanQuery => $index->orphans())->shared();
    }
}
