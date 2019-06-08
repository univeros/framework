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
     * @var string the regular expression to be matched with.
     */
    protected $pattern;
    /**
     * @var string
     */
    protected $replace;

    /**
     * RegexRule constructor.
     *
     * @param string $pattern
     * @param string $replace
     */
    public function __construct(string $pattern, string $replace)
    {
        $this->pattern = $pattern;
        $this->replace = $replace;
    }

    /**
     * @inheritDoc
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return preg_replace($this->pattern, $this->replace, $value);
    }
}
