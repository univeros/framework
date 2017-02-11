<?php
namespace Altair\Queue;

use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Queue\Contracts\AdapterInterface;
use Altair\Queue\Contracts\JobInterface;
use Altair\Queue\Contracts\ManagerInterface;
use Altair\Queue\Manager as MiddlewareManager;

class Manager implements ManagerInterface
{
    protected $adapter;
    protected $pushProcessor;
    protected $popProcessor;

    /**
     * Manager constructor.
     *
     * @param AdapterInterface $adapter
     * @param Manager|null $pushProcessor
     * @param MiddlewareInterface|null $popProcessor
     */
    public function __construct(
        AdapterInterface $adapter,
        MiddlewareManager $pushProcessor = null,
        MiddlewareInterface $popProcessor = null
    ) {
        $this->adapter = $adapter;
        $this->pushProcessor = $pushProcessor;
        $this->popProcessor = $popProcessor;
    }

    /**
     * @inheritdoc
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * @inheritdoc
     */
    public function push(PayloadInterface $payload): bool
    {
        if (null !== $this->pushProcessor) {
            $payload = call_user_func([$this->pushProcessor, '__invoke'], $payload);
        }

        return $this->adapter->push($payload);
    }

    /**
     * @inheritdoc
     */
    public function pop(string $queue = null): ?PayloadInterface
    {
        $payload = $this->adapter->pop($queue);

        return null !== $this->popProcessor
            ? call_user_func([$this->popProcessor, '__invoke'], $payload)
            : $payload;
    }

    /**
     * @inheritdoc
     */
    public function ack(PayloadInterface $payload)
    {
        if ($payload->getAttribute(JobInterface::ATTRIBUTE_COMPLETED) !== true && null !== $this->pushProcessor) {
            $payload = call_user_func([$this->pushProcessor, '__invoke'], $payload);
        }
        $this->adapter->ack($payload);
    }

    /**
     * @inheritdoc
     */
    public function isEmpty(string $queue = null): bool
    {
        return $this->adapter->isEmpty($queue);
    }

}
