<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

class LowerCaseFilter extends AbstractFilter
{
    /**
     * @var bool
     */
    protected $firstOnly;

    /**
     * LowerCaseFilter constructor.
     *
     * @param bool $firstOnly
     */
    public function __construct(bool $firstOnly = false)
    {
        $this->firstOnly = $firstOnly;
    }

    /**
     * @param mixed $value
     *
     * @return null|string
     */
    public function parse($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        return $this->firstOnly ? $this->getFirstToLower($value) : strtolower($value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getFirstToLower(string $value): string
    {
        $length = mb_strlen($value);
        if ($length === 0) {
            return '';
        }
        if ($length > 1) {
            $head = mb_substr($value, 0, 1);
            $tail = mb_substr($value, 1);

            return strtolower($head) . $tail;
        }

        return strtolower($value);
    }
}
