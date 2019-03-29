<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

interface EventInterface
{
    /**
     * Stop event propagation.
     *
     * @return EventInterface
     */
    public function stopPropagation(): EventInterface;

    /**
     * Check whether propagation was stopped.
     *
     * @return bool
     */
    public function isPropagationStopped(): bool;

    /**
     * Returns the timestamp when the event has occured.
     *
     * @return int
     */
    public function getOccurredOn(): int;

    /**
     * Get the event name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Getter for all arguments.
     *
     * @return array
     */
    public function getArguments(): array;

    /**
     * Get argument by key.
     *
     * @param string $key Key
     *
     * @throws \Altair\Happen\Exception\InvalidArgumentException If key is not found.
     * @return mixed Contents of array key
     *
     */
    public function getArgument(string $key);

    /**
     * Has Argument.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasArgument(string $key): bool;

    /**
     * Returns a new instance with a new name and same set of arguments.
     *
     * @param string $name
     *
     * @return EventInterface
     */
    public function withName(string $name): EventInterface;

    /**
     * Returns a new instance with new inserted argument.
     *
     * @param string $key
     * @param mixed $content
     *
     * @return EventInterface
     */
    public function withArgument(string $key, $content) : EventInterface;

    /**
     * Returns a new instance with new set of arguments keeping its name.
     *
     * @param array $arguments
     *
     * @return EventInterface
     */
    public function withArguments(array $arguments): EventInterface;
}
