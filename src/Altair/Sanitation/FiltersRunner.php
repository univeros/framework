<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Contracts\FiltersRunnerInterface;
use Altair\Sanitation\Contracts\ResolverInterface;
use Altair\Structure\Queue;

class FiltersRunner implements FiltersRunnerInterface
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
     * @var callable|ResolverInterface
     *
     */
    protected $resolver;

    /**
     *
     * Constructor.
     *
     * @param callable|ResolverInterface $resolver Converts queue entries to callables.
     * @param Queue $queue The middleware queue.
     */
    public function __construct(ResolverInterface $resolver = null, Queue $queue = null)
    {
        $this->resolver = $resolver;
        $this->queue = $queue;
    }

    /**
     * Calls the next entry in the queue.
     *
     * @param PayloadInterface $payload
     *
     * @return PayloadInterface
     */
    public function __invoke(PayloadInterface $payload): PayloadInterface
    {
        $entry = !$this->queue->isEmpty() ? $this->queue->pop() : null;
        $middleware = $this->resolve($entry);
        return $middleware($payload, $this);
    }

    /**
     * @param array $filters
     *
     * @return FiltersRunnerInterface
     */
    public function withFilters(array $filters): FiltersRunnerInterface
    {
        $this->queue = new Queue($filters);

        return $this;
    }

    /**
     * Converts a queue entry to a callable, using the resolver if present.
     *
     * @param mixed|callable|FilterInterface $entry the queue entry.
     *
     * @return callable|FilterInterface
     */
    protected function resolve($entry)
    {
        if (!$entry) {
            return static function (PayloadInterface $payload) {
                return $payload;
            };
        }
        if (!$this->resolver) {
            return $entry;
        }

        return call_user_func($this->resolver, $entry);
    }
}
