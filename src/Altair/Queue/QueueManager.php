<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Queue;

use Altair\Middleware\Contracts\MiddlewareManagerInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\QueueAdapterInterface;
use Altair\Queue\Contracts\QueueManagerInterface;

class QueueManager implements QueueManagerInterface
{
    protected $adapter;
    protected $pushProcessor;
    protected $popProcessor;

    /**
     * Manager constructor.
     *
     * @param QueueAdapterInterface $adapter
     * @param MiddlewareManagerInterface $pushProcessor
     * @param MiddlewareManagerInterface $popProcessor
     */
    public function __construct(
        QueueAdapterInterface $adapter,
        MiddlewareManagerInterface $pushProcessor = null,
        MiddlewareManagerInterface $popProcessor = null
    ) {
        $this->adapter = $adapter;
        $this->pushProcessor = $pushProcessor;
        $this->popProcessor = $popProcessor;
    }

    /**
     * @inheritDoc
     */
    public function getAdapter(): QueueAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @inheritDoc
     */
    public function push(PayloadInterface $payload): bool
    {
        if (null !== $this->pushProcessor) {
            $payload = call_user_func([$this->pushProcessor, '__invoke'], $payload);
        }

        return $this->adapter->push($payload);
    }

    /**
     * @inheritDoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $payload = $this->adapter->pop($queue);

        return null !== $this->popProcessor
            ? call_user_func([$this->popProcessor, '__invoke'], $payload)
            : $payload;
    }

    /**
     * @inheritDoc
     */
    public function ack(PayloadInterface $payload)
    {
        if ($payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) !== true && null !== $this->pushProcessor) {
            $payload = call_user_func([$this->pushProcessor, '__invoke'], $payload);
        }
        $this->adapter->ack($payload);
    }

    /**
     * @inheritDoc
     */
    public function isEmpty(string $queue = null): bool
    {
        return $this->adapter->isEmpty($queue);
    }
}
