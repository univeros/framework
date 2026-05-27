<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Happen\Contracts\EventDispatcherInterface;
use Altair\Happen\EventDispatcher;
use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Result\InspectionTable;
use Closure;

/**
 * Reads the Happen EventDispatcher's listener map without dispatching.
 *
 * Requires the concrete {@see EventDispatcher} (not just the PSR-14
 * interface) because the priority-sorted listener map lives on the
 * concrete class. Hosts using a different dispatcher will need to
 * register a different inspector — keep that future option open by
 * type-hinting only what we actually need.
 */
final readonly class ListenerInspector
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
        if (!$dispatcher instanceof EventDispatcher) {
            throw new IntrospectionException(\sprintf(
                'ListenerInspector requires %s, got %s. Custom dispatchers need their own inspector.',
                EventDispatcher::class,
                $dispatcher::class,
            ));
        }
    }

    public function inspectAll(): InspectionTable
    {
        $rows = [];
        foreach ($this->dispatcherConcrete()->getEventNames() as $name) {
            $rows[] = [
                'event' => $name,
                'listeners' => $this->dispatcherConcrete()->listenerCount($name),
            ];
        }

        return new InspectionTable(
            title: 'Registered event listeners',
            columns: ['event', 'listeners'],
            rows: $rows,
            extras: ['total_events' => \count($rows)],
        );
    }

    /**
     * Detail view for one event — listeners in priority order.
     */
    public function inspectOne(string $event): InspectionTable
    {
        if (!$this->dispatcherConcrete()->hasListeners($event)) {
            throw new NotFoundException(\sprintf("No listeners for event '%s'.", $event));
        }

        $rows = [];
        $position = 0;
        foreach ($this->dispatcherConcrete()->getListeners($event) as $listener) {
            $rows[] = [
                'position' => $position++,
                'listener' => $this->describeCallable($listener),
            ];
        }

        return new InspectionTable(
            title: \sprintf("Listeners for event '%s' (priority order)", $event),
            columns: ['position', 'listener'],
            rows: $rows,
        );
    }

    private function dispatcherConcrete(): EventDispatcher
    {
        /** @var EventDispatcher $d */
        $d = $this->dispatcher;

        return $d;
    }

    private function describeCallable(mixed $callable): string
    {
        if (\is_string($callable)) {
            return $callable;
        }

        if (\is_array($callable) && \count($callable) === 2 && \is_string($callable[1])) {
            $left = \is_object($callable[0]) ? $callable[0]::class : (string) $callable[0];

            return $left . '::' . $callable[1];
        }

        if ($callable instanceof Closure) {
            return 'Closure';
        }

        return \is_object($callable) ? $callable::class : '(callable)';
    }
}
