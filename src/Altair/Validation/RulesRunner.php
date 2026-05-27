<?php

declare(strict_types=1);

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
use Override;

class RulesRunner implements RulesRunnerInterface
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
    public function __construct(?ResolverInterface $resolver = null, protected ?Queue $queue = null)
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
        $entry = $this->queue instanceof Queue && !$this->queue->isEmpty() ? $this->queue->pop() : null;
        $middleware = $this->resolve($entry);

        return $middleware($payload, $this);
    }

    #[Override]
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
            return fn(PayloadInterface $payload, callable $next): PayloadInterface => $payload;
        }

        if (!$this->resolver) {
            return $entry;
        }

        return \call_user_func($this->resolver, $entry);
    }
}
