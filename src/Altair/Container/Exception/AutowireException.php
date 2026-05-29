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
 * Thrown when a constructor or callable parameter cannot be satisfied by a
 * binding, an attribute, autowiring, or a default. The resolution path is
 * appended so the failure is traceable.
 */
class AutowireException extends ContainerException
{
    /**
     * @param list<string> $path
     */
    public static function unresolvableParameter(string $parameter, string $owner, array $path): self
    {
        return new self(self::withPath(
            \sprintf('Cannot autowire parameter $%s of %s', $parameter, $owner),
            $path
        ));
    }

    /**
     * @param list<string> $path
     */
    public static function notInstantiable(string $class, array $path): self
    {
        return new self(self::withPath(
            \sprintf('Class %s is not instantiable', $class),
            $path
        ));
    }

    /**
     * @param list<string> $path
     */
    private static function withPath(string $message, array $path): string
    {
        if ($path === []) {
            return $message . '.';
        }

        return \sprintf('%s (resolving %s).', $message, implode(' -> ', $path));
    }
}
