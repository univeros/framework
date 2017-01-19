<?php
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
