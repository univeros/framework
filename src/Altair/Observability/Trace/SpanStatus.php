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
 * OTLP span status code (0 = unset, 1 = ok, 2 = error). The integer values
 * match the OpenTelemetry spec so OTLP exporters emit them unchanged.
 */
enum SpanStatus: int
{
    case Unset = 0;
    case Ok = 1;
    case Error = 2;
}
