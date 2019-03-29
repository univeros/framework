<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Structure\Queue;
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Contracts\RuleInterface;
use Altair\Validation\Contracts\RulesRunnerInterface;

class RulesRunner implements RulesRunnerInterface
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
        $entry = null !== $this->queue && !$this->queue->isEmpty() ? $this->queue->pop() : null;
        $middleware = $this->resolve($entry);

        return $middleware($payload, $this);
    }

    /**
     * @param array $rules
     *
     * @return RulesRunnerInterface
     */
    public function withRules(array $rules): RulesRunnerInterface
    {
        $this->queue = new Queue($rules);

        return $this;
    }

    /**
     * Converts a queue entry to a callable, using the resolver if present.
     *
     * @param mixed|callable|RuleInterface $entry the queue entry.
     *
     * @return callable|RuleInterface
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
