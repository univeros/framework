<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

use Altair\Happen\Exception\InvalidArgumentException;

interface EventInterface
{
    /**
     * Stop event propagation.
     */
    public function stopPropagation(): EventInterface;

    /**
     * Check whether propagation was stopped.
     */
    public function isPropagationStopped(): bool;

    /**
     * Returns the timestamp when the event has occured.
     */
    public function getOccurredOn(): int;

    /**
     * Get the event name.
     */
    public function getName(): string;

    /**
     * Getter for all arguments.
     */
    public function getArguments(): array;

    /**
     * Get argument by key.
     *
     * @param string $key Key
     *
     * @throws InvalidArgumentException If key is not found.
     * @return mixed Contents of array key
     */
    public function getArgument(string $key);

    /**
     * Has Argument.
     *
     *
     */
    public function hasArgument(string $key): bool;

    /**
     * Returns a new instance with a new name and same set of arguments.
     *
     *
     */
    public function withName(string $name): EventInterface;

    /**
     * Returns a new instance with new inserted argument.
     *
     *
     */
    public function withArgument(string $key, mixed $content) : EventInterface;

    /**
     * Returns a new instance with new set of arguments keeping its name.
     *
     *
     */
    public function withArguments(array $arguments): EventInterface;
}
