<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class InRule extends AbstractRule
{
    /**
     * @var mixed
     */
    protected $haystack;
    /**
     * @var bool
     */
    protected $strict;

    /**
     * InRule constructor.
     *
     * @param $haystack
     * @param bool $strict
     */
    public function __construct($haystack, bool $strict = false)
    {
        $this->haystack = $haystack;
        $this->strict = $strict;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        if (is_array($this->haystack)) {
            return in_array($value, $this->haystack, $this->strict);
        }

        if ($value === null || $value === '') {
            return $this->strict ? $value === $this->haystack : $value === $this->haystack;
        }

        return $this->strict
            ? false !== mb_strpos($this->haystack, $value, 0, mb_detect_encoding($value))
            : false !== mb_stripos($this->haystack, $value, 0, mb_detect_encoding($value));
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf(
            '"%s" not found in "%s".',
            $value,
            is_array($this->haystack) ? implode(', ', $this->haystack) : $this->haystack
        );
    }
}
