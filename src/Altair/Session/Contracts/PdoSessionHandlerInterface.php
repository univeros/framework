<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Contracts;

interface PdoSessionHandlerInterface extends \SessionHandlerInterface
{
    /**
     * Returns whether the session has expired or not.
     *
     * @return bool
     */
    public function getHasSessionExpired(): bool;
}
