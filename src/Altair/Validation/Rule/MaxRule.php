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

class MaxRule extends AbstractRule
{
    /**
     * MaxRule constructor.
     *
     * @param $max
     */
    public function __construct(
        /**
         * @var mixed the maximum valid value
         */
        protected mixed $max
    ) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert($value): bool
    {
        if (!\is_scalar($value)) {
            return false;
        }

        return $value <= $this->max;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage($value): string
    {
        return \sprintf('"%s" is not valid.', $value);
    }
}
