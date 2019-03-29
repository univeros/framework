<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

interface EventStackInterface
{
    /**
     * Adds an event.
     *
     * @param EventInterface|string $event
     *
     * @return self
     */
    public function addEvent($event): self;

    /**
     * Returns all added events
     *
     * @return EventInterface[]
     */
    public function getStack(): array;
}
