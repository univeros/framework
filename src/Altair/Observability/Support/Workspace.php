<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observability\Support;

use Altair\Observability\Log\JsonLogReader;

/**
 * Trait shared by the observability CLI commands: a host may bind a
 * {@see JsonLogReader} (with an explicit directory) and it's picked up;
 * otherwise one is built from the current working directory's
 * `.altair/observability/`.
 */
trait Workspace
{
    public function __construct(private readonly ?JsonLogReader $reader = null) {}

    private function reader(): JsonLogReader
    {
        return $this->reader ?? new JsonLogReader(getcwd() . '/.altair/observability');
    }
}
