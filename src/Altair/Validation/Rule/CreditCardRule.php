<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Altair\Validation\Exception\InvalidArgumentException;
use Override;

class CreditCardRule extends AbstractRule
{
    /**
     * @var array<string, array{pattern: string, length: array<int, int>}>
     */
    protected $cards = [
        'visaelectron' => [
            'pattern' => '/^4(026|17500|405|508|844|91[37])/',
            'length' => [13, 16],
        ],
        'carteblanche' => [
            'pattern' => '/^3(0[0-5]\d{11}|[68]\d{12})/',
            'length' => [14],
        ],
        'maestro' => [
            'pattern' => '/^(5(018|0[23]|[68])|6(30|7))/',
            'length' => [12, 13, 14, 15, 16, 17, 18, 19],
        ],
        'forbrugsforeningen' => [
            'pattern' => '/^600/',
            'length' => [16],
        ],
        'dankort' => [
            'pattern' => '/^5019/',
            'length' => [16],
        ],
        'visa' => [
            'pattern' => '/^4/',
            'length' => [13, 16],
        ],
        'mastercard' => [
            'pattern' => '/^(5[0-5]|2(2(2[1-9]|[3-9])|[3-6]|7(0|1|20)))/',
            'length' => [16],
        ],
        'amex' => [
            'pattern' => '/^3[47]/',
            'length' => [15],
        ],
        'dinersclub' => [
            'pattern' => '/^3[0689]/',
            'length' => [14],
        ],
        'discover' => [
            'pattern' => '/^6([045]|22)/',
            'length' => [16],
        ],
        'unionpay' => [
            'pattern' => '/^(62|88)/',
            'length' => [16, 17, 18, 19],
        ],
        'jcb' => [
            'pattern' => '/^35/',
            'length' => [16],
        ],
        'solo' => [
            'pattern' => '/^(6334|6767)|(6334|6767)|(6334|6767)/',
            'length' => [16, 18, 19],
        ],
        'switch' => [
            'pattern' => '/^(4903|4905|4911|4936|6333|6759)|(4903|4905|4911|4936|6333|6759)|' .
                '(4903|4905|4911|4936|6333|6759)|564182|564182|564182|633110|633110|633110/',
            'length' => [16, 18, 19],
        ],
    ];

    /**
     * @var array<int, string>
     */
    protected $noLuhn = ['unionpay'];

    protected string $type;

    /**
     * CreditCardRule constructor.
     */
    public function __construct(string $type)
    {
        if (!\array_key_exists($type, $this->cards)) {
            throw new InvalidArgumentException(\sprintf('Unknown credit card type: "%s".', $type));
        }

        $this->type = $type;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function assert(mixed $value): bool
    {
        $value = $this->sanitize($value);

        return $this->assertNumeric($value) && $this->assertLength($value) &&
            $this->assertPattern($value) && $this->assertLuhn($value);
    }

    #[Override]
    protected function buildErrorMessage(mixed $value): string
    {
        return \sprintf('"%s" is not a valid "%s" credit card number.', $value, $this->type);
    }

    /**
     * Checks whether the value is a valid numeric one.
     *
     *
     */
    protected function assertNumeric(string $value): bool
    {
        return (bool) preg_match('/^\d+$/', $value);
    }

    /**
     * Validates the valid lengths of the credit card number.
     *
     *
     */
    protected function assertLength(string $value): bool
    {
        foreach ($this->cards[$this->type]['length'] as $length) {
            if (\strlen($value) === $length) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates whether number matches the credit card pattern.
     *
     *
     */
    protected function assertPattern(string $value): bool
    {
        return (bool) preg_match($this->cards[$this->type]['pattern'], $value);
    }

    /**
     * Validates credit card number using mod10 variant of the luhn algorithm.
     *
     * @param string $value
     */
    protected function assertLuhn($value): bool
    {
        if (\in_array($this->type, $this->noLuhn, false)) {
            return true;
        }

        $cardNumber = strrev($value);
        $checksum = 0;
        $length = \strlen($cardNumber);
        for ($i = 0; $i < $length; $i++) {
            $currentNum = $cardNumber[$i];
            if ($i % 2 === 1) {
                $currentNum *= 2;
            }

            if ($currentNum > 9) {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $checksum += $currentNum;
        }

        return $checksum % 10 === 0;
    }

    /**
     * Removes any invalid credit card number characters from the value.
     *
     *
     */
    protected function sanitize(string $value): string
    {
        return preg_replace('/[ -]+/', '', $value);
    }
}
