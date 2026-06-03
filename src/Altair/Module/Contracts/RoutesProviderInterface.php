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
 * A module that contributes HTTP routes to the host application.
 *
 * Routes use the same shape as the host's `config/routes.php`: a list of
 * `[METHOD, PATH, ActionClass]` tuples. The HTTP front controller merges the
 * host routes with every module's routes before building the dispatcher.
 */
interface RoutesProviderInterface
{
    /**
     * @return list<array{0: string, 1: string, 2: class-string}>
     */
    public function routes(): array;
}
