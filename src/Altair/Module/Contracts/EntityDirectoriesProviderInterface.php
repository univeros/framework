<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Contracts;

/**
 * A module that ships Cycle-annotated entities.
 *
 * The directories are scanned (alongside the host's own entity directories)
 * when the runtime schema is compiled, so the module's entities participate in
 * the ORM without any host configuration.
 */
interface EntityDirectoriesProviderInterface
{
    /**
     * Absolute paths to directories containing Cycle entity classes.
     *
     * @return list<string>
     */
    public function entityDirectories(): array;
}
