<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware;

use Altair\Middleware\Contracts\MiddlewareInterface;
use Altair\Middleware\Contracts\MiddlewareResolverInterface;
use Altair\Middleware\Contracts\MiddlewareRunnerInterface;
use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Structure\Queue;
use Override;

class Runner implements MiddlewareRunnerInterface
{
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
     * @param Queue<mixed> $queue The middleware queue.
     *
     * @param callable|MiddlewareResolverInterface $resolver Converts queue entries to callables.
     *
     */
    public function __construct(protected Queue $queue, ?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Calls the next entry in the queue.
     *
     *
     */
    #[Override]
    public function __invoke(PayloadInterface $payload): PayloadInterface
    {
        $entry = $this->queue->isEmpty() ? null : $this->queue->pop();
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
            return static fn(PayloadInterface $payload, callable $next): PayloadInterface => $payload;
        }

        if (!$this->resolver) {
            return $entry;
        }

        return \call_user_func($this->resolver, $entry);
    }
}
