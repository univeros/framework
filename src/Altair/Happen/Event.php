<?php

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
     * @inheritdoc
     */
    public function getOccurredOn(): int
    {
        return $this->occurredOn;
    }

    /**
     * @inheritdoc
     */
    public function stopPropagation(): EventInterface
    {
        $this->propagationStopped = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @inheritdoc
     */
    public function hasArgument(string $key): bool
    {
        return array_key_exists(strtolower($key), $this->arguments);
    }

    /**
     * @inheritdoc
     */
    public function getArgument(string $key)
    {
        if (!$this->hasArgument($key)) {
            throw new InvalidArgumentException(sprintf('Argument "%s" not found.', $key));
        }

        return $this->arguments[strtolower($key)];
    }

    /**
     * @inheritdoc
     */
    public function withName(string $name): EventInterface
    {
        $cloned = clone $this;
        $cloned->name = $name;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function withArgument(string $key, $content): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments[strtolower($key)] = $content;

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function withArguments(array $arguments): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments = $arguments;

        return $cloned;
    }
}
