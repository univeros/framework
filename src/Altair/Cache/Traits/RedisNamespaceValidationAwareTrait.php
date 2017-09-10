<?php

namespace Altair\Cache\Traits;

use Altair\Cache\Exception\InvalidArgumentException;

trait RedisNamespaceValidationAwareTrait
{
    /**
     * @throws InvalidArgumentException if the namespace contains invalid characters
     *
     * @param string $namespace
     */
    public function useNamespace(string $namespace)
    {
        if (preg_match('/[^-+_.A-Za-z0-9]/', $namespace, $match)) {
            throw new InvalidArgumentException(
                sprintf(
                    'The namespace for %s contains "%s" but only chars in [-+_.A-Za-z0-9] are allowed.',
                    static::class,
                    $match[0]
                )
            );
        }
        $this->namespace = $namespace;
    }
}
