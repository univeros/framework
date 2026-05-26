<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen\Contracts;

interface EventStackInterface
{
    /**
     * Adds an event.
     */
    public function addEvent(string|EventInterface $event): self;

    /**
     * Returns all added events.
     *
     * @return list<string|EventInterface>
     */
    public function getStack(): array;
}
