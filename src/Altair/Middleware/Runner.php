<?php
namespace Altair\Middleware;

use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
use Altair\Structure\Queue;

class Runner
{
    /**
     * The middleware queue.
     *
     * @var Queue
     */
    protected $queue;
    /**
     *
     * A callable to convert queue entries to callables.
     *
     * @var callable|MiddlewareResolverInterface
     *
     */
    protected $resolver;

    /**
     *
     * Constructor.
     *
     * @param Queue $queue The middleware queue.
     *
     * @param callable|MiddlewareResolverInterface $resolver Converts queue entries to callables.
     *
     */
    public function __construct(Queue $queue, callable $resolver = null)
    {
        $this->queue = $queue;
        $this->resolver = $resolver;
    }

    /**
     * Calls the next entry in the queue.
     *
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload)
    {
        $entry = $this->queue->pop();
        $middleware = $this->resolve($entry);

        return $middleware($payload, $this);
    }

    /**
     * Converts a queue entry to a callable, using the resolver if present.
     *
     * @param mixed|callable|MiddlewareInterface $entry the queue entry.
     *
     * @return callable|MiddlewareInterface
     */
    protected function resolve($entry)
    {
        if (!$entry) {
            return function (PayloadInterface $payload, callable $next) {
                return $payload;
            };
        }
        if (!$this->resolver) {
            return $entry;
        }

        return call_user_func($this->resolver, $entry);
    }
}
