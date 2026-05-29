<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Exporter;

use Altair\Observability\Contracts\ExporterInterface;
use Override;

/**
 * Appends finished spans and metric points to a JSONL file under
 * `.altair/observability/`. One JSON document per line, so a long-running
 * process never rewrites the whole file and `tail -f` is the natural human
 * inspection path.
 *
 * Each line carries a `_kind` discriminator (`span` | `metric`) so a single
 * tail consumer can branch by type without parsing the whole document.
 */
final readonly class JsonLogExporter implements ExporterInterface
{
    public function __construct(private string $directory) {}

    #[Override]
    public function export(array $spans, array $metrics): void
    {
        if ($spans === [] && $metrics === []) {
            return;
        }

        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o755, true);
        }

        $path = $this->directory . '/' . date('Y-m-d') . '.jsonl';

        $lines = [];
        foreach ($spans as $span) {
            $lines[] = $this->encode(['_kind' => 'span', ...$span->toArray()]);
        }

        foreach ($metrics as $metric) {
            $lines[] = $this->encode(['_kind' => 'metric', ...$metric->toArray()]);
        }

        file_put_contents($path, implode("\n", $lines) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function encode(array $row): string
    {
        $encoded = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $encoded === false ? '{}' : $encoded;
    }
}
