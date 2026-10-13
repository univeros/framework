<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Emitter;

/**
 * How `openapi:import --group-by` fills `endpoint.resource` when the operation
 * carries no `x-altair-resource` (which always wins).
 */
enum ResourceGrouping: string
{
    /** Flat placement; the pre-#227 behaviour. */
    case None = 'none';

    /** First OpenAPI tag, falling back to the path noun. */
    case Tag = 'tag';

    /** The path noun (`/users/{id}` → `User`). */
    case Path = 'path';
}
