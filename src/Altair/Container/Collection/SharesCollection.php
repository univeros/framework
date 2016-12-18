<?php
namespace Altair\Container\Collection;


use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Map;
use InvalidArgumentException;

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
