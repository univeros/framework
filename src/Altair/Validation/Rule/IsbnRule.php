<?php
namespace Validation\Rule;

use Altair\Validation\Exception\InvalidArgumentException;
use Altair\Validation\Rule\AbstractRule;

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
        if (null !== $type && !in_array($type, [10, 13])) {
            throw new InvalidArgumentException(sprintf('ISBN type must be 10 or 13, "%d" given.', $type));
        }
        $this->type = $type;
    }

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        $value = $this->sanitize($value);

        return null !== $value && (null !== $this->type
                ? call_user_func([$this, 'assertIsbn' . (string)$this->type], $value)
                : ($this->assertIsbn10($value) || $this->assertIsbn13($value)));
    }

    /**
     * @inheritdoc
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
                $checksum += 10 * intval(10 - $i);
            }
            $checksum += intval($value[$i]) * intval(10 - $i);
        }

        return $checksum % 11 === 0;
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
        $value = preg_replace('/(?:(?!([0-9|X$])).)*/', '', $value);

        return preg_match('/^[0-9]{10,13}$|^[0-9]{9}X$/', $value) ? $value : null;
    }
}
