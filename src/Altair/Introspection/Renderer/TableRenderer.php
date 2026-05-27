<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Renderer;

use Altair\Introspection\Contracts\RendererInterface;
use Altair\Introspection\Result\InspectionTable;
use Override;

/**
 * Human-readable fixed-width text table.
 *
 * Each column is sized to fit the widest value (including the header).
 * Multi-line cell values are flattened to one line — call sites that
 * need richer rendering should ship structured data through the JSON
 * renderer instead.
 */
final readonly class TableRenderer implements RendererInterface
{
    #[Override]
    public function render(InspectionTable $table): string
    {
        if ($table->isEmpty()) {
            return $table->title . "\n  (no rows)\n";
        }

        $widths = $this->columnWidths($table);
        $lines = [];

        $lines[] = $table->title;
        $headerRow = array_combine($table->columns, $table->columns);
        $lines[] = $this->row($table->columns, $headerRow, $widths);
        $lines[] = $this->separator($widths);

        foreach ($table->rows as $row) {
            $lines[] = $this->row($table->columns, $row, $widths);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<string, int>
     */
    private function columnWidths(InspectionTable $table): array
    {
        $widths = [];
        foreach ($table->columns as $column) {
            $widths[$column] = mb_strlen($column);
        }

        foreach ($table->rows as $row) {
            foreach ($table->columns as $column) {
                $cell = $this->cellAsString($row[$column] ?? '');
                $widths[$column] = max($widths[$column], mb_strlen($cell));
            }
        }

        return $widths;
    }

    /**
     * @param list<string>                              $columns
     * @param array<string, mixed>|list<string>         $row
     * @param array<string, int>                        $widths
     */
    private function row(array $columns, array $row, array $widths): string
    {
        $parts = [];
        foreach ($columns as $column) {
            $cell = $this->cellAsString($row[$column] ?? '');
            $parts[] = $cell . str_repeat(' ', max(0, $widths[$column] - mb_strlen($cell)));
        }

        return '  ' . implode('  ', $parts);
    }

    /**
     * @param array<string, int> $widths
     */
    private function separator(array $widths): string
    {
        $parts = [];
        foreach ($widths as $w) {
            $parts[] = str_repeat('-', $w);
        }

        return '  ' . implode('  ', $parts);
    }

    private function cellAsString(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            return str_replace(["\n", "\r"], ' ', (string) $value);
        }

        return str_replace(["\n", "\r"], ' ', (string) json_encode($value, JSON_UNESCAPED_SLASHES));
    }
}
