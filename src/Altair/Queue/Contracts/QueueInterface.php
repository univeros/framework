<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Contracts;

use Altair\Middleware\Contracts\PayloadInterface;

interface QueueInterface
{
    /**
     * @param PayloadInterface $payload
     *
     * @return bool
     */
    public function push(PayloadInterface $payload): bool;

    /**
     * @param string|null $queue
     *
     * @return PayloadInterface|null
     */
    public function pop(string $queue = null): ?PayloadInterface;

    /**
     * @param PayloadInterface $payload
     */
    public function ack(PayloadInterface $payload);

    /**
     * @param string|null $queue
     *
     * @return bool
     */
    public function isEmpty(string $queue = null): bool;
}
