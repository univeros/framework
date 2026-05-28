<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Transport;

use Altair\Messaging\Configuration\TransportSettings;
use Altair\Messaging\Exception\UnknownTransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Lazily builds and caches TransportInterface instances by name from
 * the configured DSNs.
 */
final class TransportRegistry
{
    /** @var array<string, TransportInterface> */
    private array $cache = [];

    /**
     * @param TransportFactoryInterface<TransportInterface> $factory
     */
    public function __construct(
        private readonly TransportSettings $settings,
        private readonly TransportFactoryInterface $factory,
        private readonly SerializerInterface $serializer,
    ) {}

    public function get(string $name): TransportInterface
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        if (!$this->settings->hasTransport($name)) {
            throw new UnknownTransportException(\sprintf(
                "Transport '%s' is not configured (define %s%s=DSN).",
                $name,
                TransportSettings::ENV_PREFIX,
                strtoupper($name),
            ));
        }

        return $this->cache[$name] = $this->factory->createTransport(
            $this->settings->dsn($name),
            [],
            $this->serializer,
        );
    }

    public function has(string $name): bool
    {
        return $this->settings->hasTransport($name);
    }

    /**
     * @param  list<string>|null                $names
     * @return array<string, TransportInterface>
     */
    public function getMany(?array $names = null): array
    {
        $transports = [];
        foreach ($names ?? $this->settings->names() as $name) {
            $transports[$name] = $this->get($name);
        }

        return $transports;
    }
}
