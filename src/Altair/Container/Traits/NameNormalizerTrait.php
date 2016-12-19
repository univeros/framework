<?php
namespace Altair\Container\Traits;

trait NameNormalizerTrait
{
    /**
     * @param $className
     *
     * @return string
     */
    protected function normalizeName(string $className): string
    {
        return ltrim(strtolower($className), '\\');
    }
}
