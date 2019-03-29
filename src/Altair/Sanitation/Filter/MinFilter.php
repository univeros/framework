<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class MinFilter extends AbstractFilter
{
    /**
     * @var mixed the minimum valid value
     */
    protected $min;

    /**
     * MinFilter constructor.
     *
     * @param $min
     */
    public function __construct($min)
    {
        $this->min = $min;
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $value < $this->min ? $this->min : $value;
    }
}
