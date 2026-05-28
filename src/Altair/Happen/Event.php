<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Altair\Happen\Contracts\EventInterface;
use Altair\Happen\Exception\InvalidArgumentException;
use Carbon\Carbon;
use Override;

class Event implements EventInterface
{
    /**
     * @var bool Whether no further event listeners should be triggered
     */
    protected $propagationStopped = false;

    /**
     * @var array<string, mixed>
     */
    protected array $arguments;

    /**
     * @var int
     */
    protected $occurredOn;

    /**
     * Event constructor.
     *
     * @param array<string, mixed>|null $arguments
     */
    public function __construct(protected string $name, ?array $arguments = null)
    {
        $this->arguments = $arguments ?? [];
        $this->occurredOn = Carbon::today('UTC')->getTimestamp();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getOccurredOn(): int
    {
        return $this->occurredOn;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function stopPropagation(): EventInterface
    {
        $this->propagationStopped = true;

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function hasArgument(string $key): bool
    {
        return \array_key_exists(strtolower($key), $this->arguments);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getArgument(string $key)
    {
        if (!$this->hasArgument($key)) {
            throw new InvalidArgumentException(\sprintf('Argument "%s" not found.', $key));
        }

        return $this->arguments[strtolower($key)];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withName(string $name): EventInterface
    {
        $cloned = clone $this;
        $cloned->name = $name;

        return $cloned;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function withArgument(string $key, mixed $content): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments[strtolower($key)] = $content;

        return $cloned;
    }

    /**
     * @inheritDoc
     *
     * @param array<string, mixed> $arguments
     */
    #[Override]
    public function withArguments(array $arguments): EventInterface
    {
        $cloned = clone $this;
        $cloned->arguments = $arguments;

        return $cloned;
    }
}
