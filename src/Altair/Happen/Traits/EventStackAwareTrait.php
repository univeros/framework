<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen\Traits;

use Altair\Happen\Contracts\EventInterface;

trait EventStackAwareTrait
{
    /**
     * @var list<string|EventInterface> events queued for batch dispatch.
     *
     * @see \Altair\Happen\EventDispatcher::dispatchStack()
     */
    protected array $stack = [];

    public function addEvent(string|EventInterface $event): self
    {
        $this->stack[] = $event;

        return $this;
    }

    /**
     * @return list<string|EventInterface>
     */
    public function getStack(): array
    {
        return $this->stack;
    }
}
