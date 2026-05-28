<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Output;

use Altair\Suggest\Contracts\SuggestionRendererInterface;
use Altair\Suggest\Exception\SuggestException;
use Altair\Suggest\Result\SuggestionReport;
use JsonException;
use Override;

/**
 * Machine-readable JSON. Deterministic for a given {@see SuggestionReport}:
 * fixed key order, no timestamps, `duration_ms` the only varying field.
 * This is the shape an MCP tool or a CI step parses.
 */
final readonly class JsonRenderer implements SuggestionRendererInterface
{
    #[Override]
    public function render(SuggestionReport $report): string
    {
        try {
            return json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (JsonException $jsonException) {
            throw new SuggestException('Report is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}
