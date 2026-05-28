<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

use Override;

/**
 * A raw data-migration step (a backfill `UPDATE`, a copy, a dedup). The SQL is
 * carried verbatim — when it is a placeholder the plan flags it for the author
 * to complete before applying.
 */
final readonly class DataMigrationIntent implements IntentInterface
{
    public const string PLACEHOLDER = '/* TODO: backfill expression */';

    public function __construct(
        public string $table,
        public string $sql,
        public string $summary = '',
    ) {}

    public function isPlaceholder(): bool
    {
        return str_contains($this->sql, self::PLACEHOLDER);
    }

    #[Override]
    public function table(): string
    {
        return $this->table;
    }

    #[Override]
    public function kind(): IntentKind
    {
        return IntentKind::DataMigration;
    }

    #[Override]
    public function describe(): string
    {
        return $this->summary !== '' ? $this->summary : $this->sql;
    }

    #[Override]
    public function destructive(): bool
    {
        return true;
    }
}
