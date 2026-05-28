<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

/**
 * One migration's applied/pending state, read through a
 * {@see \Altair\Observatory\Contracts\MigrationStatusReaderInterface}.
 *
 * Plain, render-agnostic value so the panel never touches Cycle's own
 * migration/state types directly.
 */
final readonly class MigrationStatus
{
    public function __construct(
        public string $name,
        public bool $applied,
    ) {}
}
