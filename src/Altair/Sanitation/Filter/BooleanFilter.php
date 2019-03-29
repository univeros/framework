<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class BooleanFilter extends AbstractFilter
{
    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($filtered)
            ? (bool)$filtered
            : (bool)$value;
    }
}
