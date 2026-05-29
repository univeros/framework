<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Support;

use Altair\Profiling\Storage\FilesystemProfileStorage;

/**
 * Trait shared by the profile CLI commands: a host may bind a
 * {@see FilesystemProfileStorage} (with an explicit directory) and the trait
 * picks it up; otherwise the storage is built from the current working
 * directory's `.altair/profiles/`.
 */
trait Workspace
{
    public function __construct(private readonly ?FilesystemProfileStorage $storage = null) {}

    private function storage(): FilesystemProfileStorage
    {
        return $this->storage ?? new FilesystemProfileStorage(getcwd() . '/.altair/profiles');
    }
}
