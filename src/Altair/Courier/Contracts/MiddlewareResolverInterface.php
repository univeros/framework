<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Courier\Contracts;

interface MiddlewareResolverInterface
{
    /**
     *
     * Converts a middleware queue entry to a callable or an implementation of
     * MiddlewareInterface.
     *
     * @param mixed $entry The middleware queue entry.
     *
     * @return callable|CommandMiddlewareInterface
     *
     */
    public function __invoke($entry);
}
