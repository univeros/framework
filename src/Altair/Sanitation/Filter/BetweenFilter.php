<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class BetweenFilter extends AbstractFilter
{
    /**
     * @var mixed the minimum value
     */
    protected $min;
    /**
     * @var mixed the maximum value
     */
    protected $max;

    /**
     * BetweenFilter constructor.
     *
     * @param mixed $min
     * @param mixed $max
     */
    public function __construct($min, $max)
    {
        $this->min = $min;
        $this->max = $max;
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if ($value < $this->min) {
            return $this->min;
        }
        if ($value > $this->max) {
            return $this->max;
        }

        return $value;
    }
}
