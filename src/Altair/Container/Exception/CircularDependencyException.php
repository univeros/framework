<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Exception;

/**
 * Thrown when a dependency cycle is detected during resolution. The message
 * renders the full chain so the offending edge is obvious.
 */
class CircularDependencyException extends ContainerException
{
    /**
     * @param list<string> $path the resolution chain already in progress
     */
    public static function forPath(array $path, string $repeated): self
    {
        return new self(
            \sprintf('Circular dependency detected: %s -> %s.', implode(' -> ', $path), $repeated)
        );
    }
}
