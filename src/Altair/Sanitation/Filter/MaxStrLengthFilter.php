<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class MaxStrLengthFilter extends AbstractFilter
{
    /**
     * MaxStrLengthFilter constructor.
     */
    public function __construct(protected int $max)
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function parse($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        if (mb_strlen($value) > $this->max) {
            return mb_substr($value, 0, $this->max);
        }

        return $value;
    }
}
