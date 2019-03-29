<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cache\Traits;

use Altair\Cache\Exception\InvalidArgumentException;

trait RedisNamespaceValidationAwareTrait
{
    /**
     *
     * @param string $namespace
     * @throws InvalidArgumentException if the namespace contains invalid characters
     */
    public function useNamespace(string $namespace): void
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
