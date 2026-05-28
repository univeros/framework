<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

use Override;

class MinStrLengthFilter extends AbstractFilter
{
    protected string $pad;

    /**
     * MaxStrLengthFilter constructor.
     */
    public function __construct(protected int $min, ?string $pad = null, protected int $direction = STR_PAD_RIGHT)
    {
        $this->pad = $pad ?? ' ';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function parse(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        if (mb_strlen($value) < $this->min) {
            return str_pad($value, $this->min, $this->pad, $this->direction);
        }

        return $value;
    }
}
