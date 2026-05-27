<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\TestReporter\Output;

use Altair\TestReporter\Result\TestReport;
use JsonException;
use RuntimeException;

/**
 * Emits the {@see TestReport} as JSON to either stdout or a file.
 *
 * Output is deterministic for the same {@see TestReport} instance —
 * no random fields, sorted where order doesn't carry meaning, so CI
 * can diff against a golden fixture.
 */
final readonly class JsonWriter
{
    public function __construct(
        private ?string $outputFile = null,
    ) {}

    public function emit(TestReport $report): void
    {
        try {
            $json = json_encode(
                $report->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ) . "\n";
        } catch (JsonException $jsonException) {
            throw new RuntimeException('TestReport is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }

        if ($this->outputFile === null) {
            echo $json;

            return;
        }

        $dir = \dirname($this->outputFile);
        if ($dir !== '' && !is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new RuntimeException(\sprintf("Cannot create output directory '%s'.", $dir));
        }

        if (@file_put_contents($this->outputFile, $json, LOCK_EX) === false) {
            throw new RuntimeException(\sprintf("Cannot write JSON report to '%s'.", $this->outputFile));
        }
    }

    public function outputFile(): ?string
    {
        return $this->outputFile;
    }
}
