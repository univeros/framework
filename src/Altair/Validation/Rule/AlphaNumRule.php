<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class AlphaNumRule extends AbstractRule
{
    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return (bool)preg_match('/^[\p{L}\p{Nd}]+$/u', $value);
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" have invalid alphanumeric character(s)', $value);
    }
}
