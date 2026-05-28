<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Override;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Type-keyed, priority-ordered PSR-14 listener provider.
 *
 * Listeners are registered against an event class or interface name. An event
 * matches a registration when it is an instance of that type, so listeners bound
 * to a parent class or interface also fire for subclasses. Matching listeners
 * are returned highest-priority first; ties keep registration order.
 *
 * This is the object-based counterpart to the name-keyed {@see EventDispatcher};
 * the two are independent and a host binds whichever it needs.
 */
final class Psr14ListenerProvider implements ListenerProviderInterface
{
    public const int HIGH_PRIORITY = 100;

    public const int NORMAL_PRIORITY = 0;

    public const int LOW_PRIORITY = -100;

    /**
     * @var array<class-string, list<array{priority: int, sequence: int, listener: callable}>>
     */
    private array $listeners = [];

    private int $sequence = 0;

    /**
     * Register a listener for an event type (class or interface name).
     *
     * @param class-string $eventType
     */
    public function listen(
        string $eventType,
        callable $listener,
        int $priority = self::NORMAL_PRIORITY
    ): self {
        $this->listeners[$eventType][] = [
            'priority' => $priority,
            'sequence' => $this->sequence++,
            'listener' => $listener,
        ];

        return $this;
    }

    /**
     * @return iterable<int, callable>
     */
    #[Override]
    public function getListenersForEvent(object $event): iterable
    {
        $matched = [];
        foreach ($this->listeners as $type => $entries) {
            if ($event instanceof $type) {
                foreach ($entries as $entry) {
                    $matched[] = $entry;
                }
            }
        }

        usort(
            $matched,
            static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']
                ?: $a['sequence'] <=> $b['sequence'],
        );

        foreach ($matched as $entry) {
            yield $entry['listener'];
        }
    }
}
