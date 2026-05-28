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

class BooleanRule extends AbstractRule
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        if (!\is_scalar($value)) {
            return false;
        }

        return \is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf('"%s" is not a valid boolean value.', $value);
    }
}
