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
 * In-memory representation of one Altair YAML spec file produced by the
 * {@see Emitter}. The CLI handles I/O; this class never touches disk.
 */
final readonly class EmittedSpec
{
    public function __construct(
        public string $relativePath,
        public string $contents,
    ) {}
}
