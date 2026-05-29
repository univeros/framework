<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Resolution;

use Altair\Container\Exception\CircularDependencyException;

/**
 * Tracks the chain of ids currently being constructed. It powers both
 * circular-dependency detection and the readable "A -> B -> C" path in error
 * messages. Callers must `enter()`/`leave()` in a `try/finally` so a failed
 * resolution never leaves a stale guard (the bug that plagued the old
 * container's `$making` map).
 */
final class ResolutionStack
{
    /**
     * @var list<string>
     */
    private array $stack = [];

    /**
     * @var array<string, true>
     */
    private array $active = [];

    /**
     * @throws CircularDependencyException
     */
    public function enter(string $id): void
    {
        if (isset($this->active[$id])) {
            throw CircularDependencyException::forPath($this->stack, $id);
        }

        $this->stack[] = $id;
        $this->active[$id] = true;
    }

    public function leave(string $id): void
    {
        unset($this->active[$id]);

        $index = array_search($id, $this->stack, true);
        if ($index !== false) {
            array_splice($this->stack, $index, 1);
        }
    }

    public function current(): ?string
    {
        $count = \count($this->stack);

        return $count > 0 ? $this->stack[$count - 1] : null;
    }

    /**
     * @return list<string>
     */
    public function path(): array
    {
        return $this->stack;
    }
}
