<?php
namespace Altair\Happen\Traits;

use Altair\Happen\EventInterface;
use Altair\Happen\Exception\InvalidArgumentException;

trait EventStackAwareTrait
{
    /**
     * @var array events stack that can be later used for batch dispatch.
     *
     * @see \Altair\Happen\EventDispatcher::dispatchStack()
     */
    protected $stack = [];

    /**
     * @inheritdoc
     */
    public function addEvent($event): self
    {
        if (!is_string($event) || !($event instanceof EventInterface)) {
            throw new InvalidArgumentException(
                sprintf('"%s" must be a string or an instance of "%s"', $event, EventInterface::class)
            );
        }

        $this->stack[] = $event;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getStack(): array
    {
        return $this->stack;
    }
}
