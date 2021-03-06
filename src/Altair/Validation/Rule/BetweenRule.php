<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class BetweenRule extends AbstractRule
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
     * BetweenRule constructor.
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
     * @inheritDoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return $value >= $this->min && $value <= $this->max;
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not between "%s" and "%s"', $value, $this->min, $this->max);
    }
}
