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

class InRule extends AbstractRule
{
    /**
     * InRule constructor.
     *
     * @param $haystack
     */
    public function __construct(protected mixed $haystack, protected bool $strict = false) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        if (\is_array($this->haystack)) {
            return \in_array($value, $this->haystack, $this->strict);
        }

        if ($value === null || $value === '') {
            return $this->strict ? $value === $this->haystack : $value == $this->haystack;
        }

        $value = (string) $value;
        $encoding = mb_detect_encoding($value) ?: null;

        return $this->strict
            ? false !== mb_strpos((string) $this->haystack, $value, 0, $encoding)
            : false !== mb_stripos((string) $this->haystack, $value, 0, $encoding);
    }

    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf(
            '"%s" not found in "%s".',
            $value,
            \is_array($this->haystack) ? implode(', ', $this->haystack) : $this->haystack
        );
    }
}
