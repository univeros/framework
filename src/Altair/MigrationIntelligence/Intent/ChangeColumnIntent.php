<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Intent;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Override;

/**
 * A change to an existing column's definition: type, size, nullability, or
 * default. `incompatible` marks a type change whose existing data may not cast
 * cleanly (e.g. string -> integer), which drives two-phase planning.
 */
final readonly class ChangeColumnIntent implements IntentInterface
{
    public function __construct(
        public string $table,
        public ColumnShape $before,
        public ColumnShape $after,
        public bool $incompatible = false,
    ) {}

    #[Override]
    public function table(): string
    {
        return $this->table;
    }

    #[Override]
    public function kind(): IntentKind
    {
        return IntentKind::ChangeColumn;
    }

    public function typeChanged(): bool
    {
        return !$this->before->sameTypeAs($this->after);
    }

    public function tightensNullability(): bool
    {
        return $this->before->nullable && !$this->after->nullable;
    }

    #[Override]
    public function describe(): string
    {
        $parts = [];
        if ($this->typeChanged()) {
            $parts[] = \sprintf('type %s -> %s', $this->before->type, $this->after->type);
        }

        if ($this->before->nullable !== $this->after->nullable) {
            $parts[] = $this->after->nullable ? 'DROP NOT NULL' : 'SET NOT NULL';
        }

        if ($this->before->default !== $this->after->default || $this->before->hasDefault !== $this->after->hasDefault) {
            $parts[] = 'default changed';
        }

        return \sprintf('ALTER COLUMN %s (%s)', $this->after->name, implode(', ', $parts));
    }

    #[Override]
    public function destructive(): bool
    {
        return $this->incompatible;
    }
}
