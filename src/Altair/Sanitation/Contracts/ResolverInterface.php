<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Contracts;

interface ResolverInterface
{
    /**
     *
     * Converts a middleware queue filter entry to an implementation of
     * FilterInterface.
     *
     * @param mixed $entry The middleware sanitation queue entry.
     *
     * @return FilterInterface
     */
    public function __invoke($entry): FilterInterface;
}
