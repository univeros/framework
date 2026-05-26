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

class RegexRule extends AbstractRule
{
    /**
     * RegexRule constructor.
     */
    public function __construct(
        /**
         * @var string the regular expression to be matched with.
         */
        protected string $pattern
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

        return (bool) preg_match($this->pattern, (string) $value);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage($value): string
    {
        return \sprintf('"%s" is invalid for pattern "%s".', $value, $this->pattern);
    }
}
