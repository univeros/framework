<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Worker;

use Altair\Messaging\Configuration\TransportSettings;
use Altair\Messaging\Exception\InvalidArgumentException;
use Altair\Messaging\Transport\TransportRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface as SymfonyMessageBusInterface;
use Symfony\Component\Messenger\Worker;

/**
 * Builds a Symfony Worker pointed at a specific set of transports. Kept
 * separate from the CLI command so the command stays focused on
 * argument parsing and the Worker can be unit-tested in isolation.
 */
final readonly class WorkerFactory
{
    public function __construct(
        private TransportSettings $settings,
        private TransportRegistry $registry,
        private SymfonyMessageBusInterface $bus,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * @param list<string>|null $transportNames Defaults to every configured transport
     */
    public function create(?array $transportNames = null): Worker
    {
        $names = $transportNames ?? $this->settings->names();
        if ($names === []) {
            throw new InvalidArgumentException('No transports configured; set MESSENGER_TRANSPORT_DEFAULT=DSN at minimum.');
        }

        $receivers = [];
        foreach ($names as $name) {
            $receivers[$name] = $this->registry->get($name);
        }

        return new Worker(
            receivers: $receivers,
            bus: $this->bus,
            eventDispatcher: $this->eventDispatcher,
            logger: $this->logger,
        );
    }
}
