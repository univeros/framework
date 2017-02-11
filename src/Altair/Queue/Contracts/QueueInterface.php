<?php
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
