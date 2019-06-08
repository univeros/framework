<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getStack(): array
    {
        return $this->stack;
    }
}
