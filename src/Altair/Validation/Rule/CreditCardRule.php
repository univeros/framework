<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Altair\Validation\Exception\InvalidArgumentException;

class CreditCardRule extends AbstractRule
{
    /**
     * @var array
     */
    protected $cards = [
        'visaelectron' => [
            'pattern' => '/^4(026|17500|405|508|844|91[37])/',
            'length' => [13, 16],
        ],
        'carteblanche' => [
            'pattern' => '/^3(0[0-5][0-9]{11}|[68][0-9]{12})/',
            'length' => [14]
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
            'length' => [15]
        ],
        'dinersclub' => [
            'pattern' => '/^3[0689]/',
            'length' => [14]
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
            'length' => [16, 18, 19]
        ],
        'switch' => [
            'pattern' => '/^(4903|4905|4911|4936|6333|6759)|(4903|4905|4911|4936|6333|6759)|' .
                '(4903|4905|4911|4936|6333|6759)|564182|564182|564182|633110|633110|633110/',
            'length' => [16, 18, 19]
        ]
    ];

    /**
     * @var array
     */
    protected $noLuhn = ['unionpay'];

    /**
     * @var string
     */
    protected $type;

    /**
     * CreditCardRule constructor.
     *
     * @param string $type
     */
    public function __construct(string $type)
    {
        if (!array_key_exists($type, $this->cards)) {
            throw new InvalidArgumentException(sprintf('Unknown credit card type: "%s".', $type));
        }
        $this->type = $type;
    }

    /**
     * @inheritDoc
     */
    public function assert($value): bool
    {
        $value = $this->sanitize($value);

        return $this->assertNumeric($value) && $this->assertLength($value) &&
            $this->assertPattern($value) && $this->assertLuhn($value);
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid "%s" credit card number.', $value, $this->type);
    }

    /**
     * Checks whether the value is a valid numeric one.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function assertNumeric(string $value): bool
    {
        return preg_match('/^[0-9]+$/', $value);
    }

    /**
     * Validates the valid lengths of the credit card number.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function assertLength(string $value): bool
    {
        foreach ($this->cards[$this->type]['length'] as $length) {
            if (strlen($value) === $length) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validates whether number matches the credit card pattern.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function assertPattern(string $value): bool
    {
        return preg_match($this->cards[$this->type]['pattern'], $value);
    }

    /**
     * Validates credit card number using mod10 variant of the luhn algorithm.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function assertLuhn($value): bool
    {
        if (in_array($this->type, $this->noLuhn)) {
            return true;
        }

        $cardNumber = strrev($value);
        $checksum = 0;
        for ($i = 0; $i < strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);
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
     * @param string $value
     *
     * @return string
     */
    protected function sanitize(string $value): string
    {
        return preg_replace('/[ -]+/', '', $value);
    }
}
