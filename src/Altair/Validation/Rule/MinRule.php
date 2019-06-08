<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class MinRule extends AbstractRule
{
    /**
     * @var mixed the minimum valid value
     */
    protected $min;

    /**
     * MaxRule constructor.
     *
     * @param $min
     */
    public function __construct($min)
    {
        $this->min = $min;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return $value >= $this->min;
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not valid.', $value);
    }
}
