<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class TrimFilter extends AbstractFilter
{
    /**
     * @var string
     */
    protected $chars;

    /**
     * TrimFilter constructor.
     *
     * @param string|null $chars
     */
    public function __construct(string $chars = null)
    {
        $this->chars = $chars?? " \t\n\r\0\x0B";
    }

    /**
     * @inheritDoc
     */
    public function parse($value)
    {
        if (!is_string($value)) {
            return null;
        }

        return trim($value, $this->chars);
    }
}
