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
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * PSR-11 adapter that lets Symfony's SendersLocator resolve transport
 * names through our TransportRegistry without leaking framework Container
 * details.
 */
final readonly class TransportLocator implements ContainerInterface
{
    public function __construct(private TransportRegistry $registry) {}

    #[Override]
    public function get(string $id): TransportInterface
    {
        if (!$this->registry->has($id)) {
            throw new class(\sprintf("Transport '%s' is not configured.", $id)) extends UnknownTransportException implements NotFoundExceptionInterface {};
        }

        return $this->registry->get($id);
    }

    #[Override]
    public function has(string $id): bool
    {
        return $this->registry->has($id);
    }
}
