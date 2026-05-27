<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Result;

use Altair\Introspection\Exception\IntrospectionException;

/**
 * Tabular projection of one inspection result.
 *
 * Inspectors return one of these so both renderers (table + JSON) can
 * share a single shape without each inspector having to know about
 * presentation. The `columns` list is authoritative — the renderer
 * iterates it to project each row, so missing keys become empty cells.
 */
final readonly class InspectionTable
{
    /**
     * @param list<string>                  $columns Column order for tabular rendering.
     * @param list<array<string, mixed>>    $rows    Row data, keyed by column name.
     * @param array<string, mixed>          $extras  Optional sidecar data shown only in JSON mode (totals, source paths, etc.).
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
        public array $extras = [],
    ) {
        foreach ($columns as $column) {
            if (!\is_string($column) || $column === '') {
                throw new IntrospectionException('Inspection columns must be non-empty strings.');
            }
        }
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * @return array{ title: string, columns: list<string>, rows: list<array<string, mixed>>, extras?: array<string, mixed> }
     */
    public function toArray(): array
    {
        $out = [
            'title' => $this->title,
            'columns' => $this->columns,
            'rows' => $this->rows,
        ];
        if ($this->extras !== []) {
            $out['extras'] = $this->extras;
        }

        return $out;
    }
}
