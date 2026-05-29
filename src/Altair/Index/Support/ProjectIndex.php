<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Support;

use Altair\Index\Builder\BuildResult;
use Altair\Index\Builder\IndexBuilder;
use Altair\Index\Builder\IndexConfig;
use Altair\Index\Builder\SourceScanner;
use Altair\Index\Query\ImpactQuery;
use Altair\Index\Query\OrphanQuery;
use Altair\Index\Query\UsageQuery;
use Altair\Index\Storage\Connection;
use Altair\Index\Storage\SqliteStorage;
use PDO;

/**
 * A lazy facade tying together the index for one project: it opens a single
 * shared connection on first use and hands out the builder and the read
 * queries against it. CLI commands build one from the current working
 * directory; hosts can bind one (with an explicit root) into the container.
 */
final class ProjectIndex
{
    private ?PDO $pdo = null;

    public function __construct(private readonly IndexConfig $config) {}

    public static function fromCwd(?string $databasePath = null): self
    {
        return new self(IndexConfig::forRoot((string) getcwd(), $databasePath));
    }

    public function config(): IndexConfig
    {
        return $this->config;
    }

    public function exists(): bool
    {
        return is_file($this->config->databasePath);
    }

    public function builder(): IndexBuilder
    {
        return new IndexBuilder($this->config, $this->storage(), new SourceScanner($this->config));
    }

    public function storage(): SqliteStorage
    {
        return new SqliteStorage($this->pdo());
    }

    public function usages(): UsageQuery
    {
        return new UsageQuery($this->pdo());
    }

    public function impact(): ImpactQuery
    {
        return new ImpactQuery($this->pdo());
    }

    public function orphans(): OrphanQuery
    {
        return new OrphanQuery($this->pdo());
    }

    /**
     * Bring the index up to date before a query: an incremental rebuild when a
     * database already exists, a full build the first time.
     */
    public function ensureFresh(): BuildResult
    {
        return $this->builder()->build($this->exists());
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Connection::open($this->config->databasePath);
    }
}
