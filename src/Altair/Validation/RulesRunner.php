<?php
namespace Altair\Validation;

use Altair\Structure\Queue;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Contracts\ResolverInterface;
use Altair\Validation\Contracts\RuleInterface;

class RulesRunner
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
     * @param array $rules
     *
     * @return RulesRunner
     */
    public function withRules(array $rules): RulesRunner
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
