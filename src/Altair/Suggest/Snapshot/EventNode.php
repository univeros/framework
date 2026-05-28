<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Snapshot;

/**
 * One registered event, projected from the ListenerInspector.
 *
 * `listenerTargets` carries the class portion of each listener callable so a
 * service used *only* as an event listener is not mistaken for dead code.
 */
final readonly class EventNode
{
    /**
     * @param list<string> $listenerTargets
     */
    public function __construct(
        public string $event,
        public int $listeners,
        public array $listenerTargets = [],
    ) {}
}
