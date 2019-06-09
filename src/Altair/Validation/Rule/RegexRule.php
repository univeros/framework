<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class RegexRule extends AbstractRule
{
    /**
     * @var string the regular expression to be matched with.
     */
    protected $pattern;

    /**
     * RegexRule constructor.
     *
     * @param string $pattern
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return (bool)preg_match($this->pattern, (string)$value);
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is invalid for pattern "%s".', $value, $this->pattern);
    }
}
