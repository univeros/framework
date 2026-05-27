<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor\Output;

use Altair\Doctor\Contracts\ReportRendererInterface;
use Altair\Doctor\Exception\DoctorException;
use Altair\Doctor\Result\Report;
use JsonException;
use Override;

/**
 * Machine-readable JSON. Deterministic for a given {@see Report}: fixed key
 * order, no timestamps in check detail, `duration_ms` the only varying
 * field. Agents parse this directly.
 */
final readonly class JsonRenderer implements ReportRendererInterface
{
    #[Override]
    public function render(Report $report): string
    {
        try {
            return json_encode(
                $report->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (JsonException $jsonException) {
            throw new DoctorException('Report is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}
