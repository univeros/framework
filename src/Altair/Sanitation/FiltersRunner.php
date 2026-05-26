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
    public function __construct(ResolverInterface $resolver = null, protected ?Queue $queue = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Calls the next entry in the queue.
     *
     *
     */
    #[\Override]
    public function __invoke(PayloadInterface $payload): PayloadInterface
    {
        $entry = $this->queue->isEmpty() ? null : $this->queue->pop();
        $middleware = $this->resolve($entry);
        return $middleware($payload, $this);
    }


    #[\Override]
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
            return static fn(PayloadInterface $payload): PayloadInterface => $payload;
        }

        if (!$this->resolver) {
            return $entry;
        }

        return call_user_func($this->resolver, $entry);
    }
}
