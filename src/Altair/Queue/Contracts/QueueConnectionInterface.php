<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue\Contracts;

interface QueueConnectionInterface
{
    /**
     * Connects to the queue store.
     *
     * @return QueueConnectionInterface
     */
    public function connect(): QueueConnectionInterface;

    /**
     * Disconnects the queue store.
     *
     * @return bool
     */
    public function disconnect(): bool;

    /**
     * Returns the internal store connection instance.
     *
     * @return mixed
     */
    public function getInstance();
}
