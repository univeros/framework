<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Altair\Happen\Exception\InvalidArgumentException;
use Carbon\Carbon;

class Event implements EventInterface
{
    /**
     * @var bool Whether no further event listeners should be triggered
     */
    protected $propagationStopped = false;
    /**
     * @var string
     */
    protected $name;
    /**
     * @var array
     */
    protected $arguments;
    /**
     * @var int
     */
    protected $occurredOn;

    /**
     * Event constructor.
     *
     * @param string $name
     * @param array|null $arguments
     */
    public function __construct(string $name, array $arguments = null)
    {
        $this->name = $name;
        $this->arguments = $arguments ?? [];
        $this->occurredOn = Carbon::today('UTC')->getTimestamp();
    }

    /**
     * @inheritDoc
     */
    public function getOccurredOn(): int
    {
        return $this->occurredOn;
    }

    /**
     * @inheritDoc
     */
    public function stopPropagation(): EventInterface
    {
        $this->propagationStopped = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    public function hasArgument(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->arguments);
    }

    /**
     * @inheritDoc
     */
    public function getArgument(string $key)
    {
        if (!$this->hasArgument($key)) {
            throw new InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
        }

        return $this->arguments[strtolower($key)];
    }

    /**
     * @inheritDoc
     */
    public function withName(string $name): EventInterface
    {
        $cloned = clone $this;
        $cloned->name = $name;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    public function withArgument(string $key, $content): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments[strtolower($key)] = $content;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    public function withArguments(array $arguments): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments = $arguments;

        return $cloned;
    }
}
