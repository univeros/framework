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
     * BetweenFilter constructor.
     */
    public function __construct(protected mixed $min, protected mixed $max)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
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
