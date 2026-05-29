<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Trace;

/**
 * OTLP span kind. The wire-format integer matches the OpenTelemetry spec so
 * the OTLP-JSON exporter emits values an OTel collector accepts unchanged.
 */
enum SpanKind: int
{
    case Internal = 1;
    case Server = 2;
    case Client = 3;
    case Producer = 4;
    case Consumer = 5;
}
