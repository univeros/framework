<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Override;

class MinRule extends AbstractRule
{
    /**
     * MaxRule constructor.
     *
     * @param $min
     */
    public function __construct(
        /**
         * @var mixed the minimum valid value
         */
        protected mixed $min
    ) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        if (!\is_scalar($value)) {
            return false;
        }

        return $value >= $this->min;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf('"%s" is not valid.', $value);
    }
}
