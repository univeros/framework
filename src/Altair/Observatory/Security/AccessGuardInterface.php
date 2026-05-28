<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Security;

/**
 * Decides whether the Observatory panel may be served.
 *
 * Observatory exposes health, configuration, queues and database state, so it
 * must never be reachable in production by default. Hosts can swap this for an
 * auth-aware guard (IP allow-list, signed cookie, RBAC) without touching the
 * panels themselves.
 */
interface AccessGuardInterface
{
    public function allows(): bool;
}
