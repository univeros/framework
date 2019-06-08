<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class MaxFilter extends AbstractFilter
{
    /**
     * @var mixed the maximum valid value
     */
    protected $max;

    /**
     * MaxRule constructor.
     *
     * @param $max
     */
    public function __construct($max)
    {
        $this->max = $max;
    }

    /**
     * @inheritDoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $value > $this->max ? $this->max : $value;
    }
}
