<?php
namespace Altair\Happen;

interface ListenerInterface
{
    /**
     * Handles an event.
     *
     * @param EventInterface $event
     */
    public function __invoke(EventInterface $event);
}
