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

class BetweenRule extends AbstractRule
{
    /**
     * BetweenRule constructor.
     */
    public function __construct(protected mixed $min, protected mixed $max) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        if (!\is_scalar($value)) {
            return false;
        }

        return $value >= $this->min && $value <= $this->max;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf('"%s" is not between "%s" and "%s"', $value, $this->min, $this->max);
    }
}
