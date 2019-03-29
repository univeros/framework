<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Container\Collection;

use Altair\Container\Exception\InvalidArgumentException;
use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Map;

class SharesCollection extends Map
{
    use NameNormalizerTrait;

    /**
     * @param string $name
     * @param AliasesCollection $aliasesCollection
     *
     * @return \Altair\Structure\Contracts\MapInterface
     */
    public function shareClass(string $name, AliasesCollection $aliasesCollection)
    {
        list(, $normalizedName) = $aliasesCollection->resolve($name);

        return $this->put($normalizedName, $this[$normalizedName]?? null);
    }

    /**
     * @param $instance
     * @param AliasesCollection $aliasesCollection
     *
     * @return \Altair\Structure\Contracts\MapInterface
     */
    public function shareInstance($instance, AliasesCollection $aliasesCollection)
    {
        $normalizedName = $this->normalizeName(get_class($instance));
        if (isset($aliasesCollection[$normalizedName])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot share class %s because it is currently aliased to %s',
                    $normalizedName,
                    $aliasesCollection->get($normalizedName)
                )
            );
        }

        return $this->put($normalizedName, $instance);
    }
}
