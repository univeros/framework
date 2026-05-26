<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Writer;

final readonly class WriteOutcome
{
    public function __construct(
        public string $relativePath,
        public WriteStatus $status,
    ) {}
}
