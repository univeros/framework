<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Linter;

final readonly class DriftFinding
{
    public function __construct(
        public DriftKind $kind,
        public string $message,
        public string $location,
    ) {}
}
