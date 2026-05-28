<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Snapshot;

/**
 * One scaffolder spec on disk, projected from the SpecInspector. `method`
 * and `route` are the endpoint coordinates a route is matched against.
 */
final readonly class SpecNode
{
    public function __construct(
        public string $path,
        public string $method,
        public string $route,
    ) {}
}
