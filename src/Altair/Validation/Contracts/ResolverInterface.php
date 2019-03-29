<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Contracts;

interface ResolverInterface
{
    /**
     *
     * Converts a middleware queue rule entry to an implementation of
     * RuleInterface.
     *
     * @param mixed $entry The middleware rule queue entry.
     *
     * @return RuleInterface
     */
    public function __invoke($entry): RuleInterface;
}
