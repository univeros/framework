<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

final readonly class EmittedFile
{
    public function __construct(
        public string $relativePath,
        public string $contents,
        public EmittedFileKind $kind,
    ) {}
}
