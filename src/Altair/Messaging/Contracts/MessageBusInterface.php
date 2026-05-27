<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Contracts;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Framework-owned bus contract. Wraps Symfony Messenger's interface so
 * application code never imports `Symfony\*` directly.
 */
interface MessageBusInterface
{
    /**
     * Dispatch a message and return the resulting envelope.
     *
     * @param array<StampInterface> $stamps Extra envelope stamps (routing, delay, etc.)
     */
    public function dispatch(object $message, array $stamps = []): Envelope;
}
