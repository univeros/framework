<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Collection;

use Altair\Container\Exception\InvalidArgumentException;
use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Contracts\MapInterface;
use Altair\Structure\Map;

/**
 * @extends Map<string, object|null>
 */
class SharesCollection extends Map
{
    use NameNormalizerTrait;

    /**
     * @return MapInterface<string, object|null>
     */
    public function shareClass(string $name, AliasesCollection $aliasesCollection): MapInterface
    {
        [, $normalizedName] = $aliasesCollection->resolve($name);

        return $this->put($normalizedName, $this[$normalizedName] ?? null);
    }

    /**
     * @return MapInterface<string, object|null>
     */
    public function shareInstance(object $instance, AliasesCollection $aliasesCollection): MapInterface
    {
        $normalizedName = $this->normalizeName($instance::class);
        if (isset($aliasesCollection[$normalizedName])) {
            throw new InvalidArgumentException(
                \sprintf(
                    'Cannot share class %s because it is currently aliased to %s',
                    $normalizedName,
                    $aliasesCollection->get($normalizedName)
                )
            );
        }

        return $this->put($normalizedName, $instance);
    }
}
