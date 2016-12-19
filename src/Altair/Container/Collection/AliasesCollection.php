<?php
namespace Altair\Container\Collection;

use Altair\Container\Exception\InvalidArgumentException;
use Altair\Container\Traits\NameNormalizerTrait;
use Altair\Structure\Map;

class AliasesCollection extends Map
{
    use NameNormalizerTrait;

    /**
     * Define an alias for all occurrences of a given typehint
     *
     * Use this method to specify implementation classes for interface and abstract class typehints.
     *
     * @param string $original The typehint to replace
     * @param string $alias The implementation name
     * @param SharesCollection $sharesCollection
     *
     * @throws \InvalidArgumentException if any argument is empty or not a string
     * @return self
     */
    public function define(string $original, string $alias, SharesCollection &$sharesCollection)
    {
        if ((empty($original) || !is_string($original)) || (empty($alias) || !is_string($alias))) {
            throw new InvalidArgumentException('"$original" and/or "$alias" cannot be empty.');
        }

        $original = $this->normalizeName($original);

        if (isset($sharesCollection[$original])) {
            throw new InvalidArgumentException(
                sprintf(
                    'Cannot alias class %s to %s because it is currently shared',
                    $this->normalizeName(get_class($sharesCollection->get($original))),
                    $alias
                )
            );
        }

        if ($sharesCollection->hasKey($original)) {
            $alias = $this->normalizeName($alias);
            $sharesCollection->put($alias, null)->remove($original);
        }

        return $this->put($original, $alias);
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function resolve(string $name): array
    {
        $normalizedName = $this->normalizeName($name);

        if (isset($this[$normalizedName])) {
            $name = $this->get($normalizedName);
            $normalizedName = $this->normalizeName($name);
        }

        return [$name, $normalizedName];
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getNormalized($name)
    {
        return $this->normalizeName($this->get($name));
    }
}
