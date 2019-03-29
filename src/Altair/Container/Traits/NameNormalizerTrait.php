<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
