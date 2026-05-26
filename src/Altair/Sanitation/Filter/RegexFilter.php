<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class RegexFilter extends AbstractFilter
{

    /**
     * RegexRule constructor.
     */
    public function __construct(
        /**
         * @var string the regular expression to be matched with.
         */
        protected string $pattern,
        protected string $replace
    )
    {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function parse($value): null|string|array
    {
        if (!is_scalar($value)) {
            return null;
        }

        return preg_replace($this->pattern, $this->replace, (string) $value);
    }
}
