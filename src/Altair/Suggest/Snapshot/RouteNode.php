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
 * One registered HTTP route, projected from the RouteInspector.
 */
final readonly class RouteNode
{
    public function __construct(
        public string $method,
        public string $path,
        public string $action,
    ) {}
}
