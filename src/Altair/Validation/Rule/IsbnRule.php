<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Altair\Validation\Exception\InvalidArgumentException;

class IsbnRule extends AbstractRule
{
    /**
     * @var int
     */
    protected $type;

    /**
     * IsbnRule constructor.
     *
     * @param int|null $type
     */
    public function __construct(int $type = null)
    {
        if (null !== $type && !in_array($type, [10, 13], false)) {
            throw new InvalidArgumentException(sprintf('ISBN type must be 10 or 13, "%d" given.', $type));
        }
        $this->type = $type;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        $value = $this->sanitize((string)$value);

        return null !== $value && (null !== $this->type
                ? $this->{'assertIsbn' . $this->type}($value)
                : ($this->assertIsbn10($value) || $this->assertIsbn13($value)));
    }

    /**
     * @inheritDoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid ISBN number.', $value);
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function assertIsbn10(string $value): bool
    {
        if (strlen($value) !== 10) {
            return false;
        }
        if (!preg_match('/\\d{9}[0-9xX]/i', $value)) {
            return false;
        }
        $checksum = 0;
        for ($i = 0; $i < 10; ++$i) {
            if ($value[$i] === 'X') {
                $checksum += 10 * (10 - $i);
            }
            $checksum += (int)$value[$i] * (10 - $i);
        }

        return (int)$checksum % 11 === 0;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function assertIsbn13(string $value): bool
    {
        $length = strlen($value);

        if ($length !== 13 || !ctype_digit($value)) {
            return false;
        }
        if (!preg_match('/\\d{13}/i', $value)) {
            return false;
        }
        $checksum = 0;
        for ($i = 0; $i < $length; $i += 2) {
            if ($length % 2 === 0) {
                $checksum += 3 * (int)substr($value, $i, 1);
                $checksum += (int)substr($value, $i + 1, 1);
            } else {
                $checksum += (int)substr($value, $i, 1);
                $checksum += 3 * (int)substr($value, $i + 1, 1);
            }
        }

        return $checksum % 10 === 0;
    }

    /**
     * @param string $value
     *
     * @return null|string
     */
    protected function sanitize(string $value): ?string
    {
        $value = preg_replace('/[-‐\s+]*/u', '', $value);

        return (bool)preg_match('/^\d{10,13}$|^\d{9}X$/', $value) ? $value : null;
    }
}
