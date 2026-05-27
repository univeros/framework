<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Transport;

use Altair\Messaging\Exception\UnknownTransportException;
use Override;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * PSR-11 view that maps every known receiver name to a single failure
 * transport sender. Empty when no failure transport is configured.
 */
final readonly class FailureSenderContainer implements ContainerInterface
{
    /**
     * @param list<string> $receiverNames Transports whose failures should be redirected
     */
    public function __construct(
        private array $receiverNames,
        private ?TransportInterface $failureTransport = null,
    ) {}

    #[Override]
    public function get(string $id): SenderInterface
    {
        if (!$this->has($id) || $this->failureTransport === null) {
            throw new class(\sprintf("No failure transport configured for receiver '%s'.", $id)) extends UnknownTransportException implements NotFoundExceptionInterface {};
        }

        return $this->failureTransport;
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->failureTransport !== null && \in_array($id, $this->receiverNames, true);
    }
}
