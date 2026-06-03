<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Migration;

/**
 * One directory of migration files together with the PHP namespace its
 * migration classes are declared in.
 *
 * Each module owns its own migration namespace (e.g.
 * `Acme\UserManagement\Database\Migrations`) so migration class names from
 * different modules never collide and stay autoloadable.
 */
final readonly class MigrationSource
{
    public function __construct(
        public string $directory,
        public string $namespace,
    ) {}
}
