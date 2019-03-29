<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

class IbanRule extends AbstractRule
{
    /**
     * @var array
     */
    protected $patterns = [
        'AL' => '[0-9]{8}[0-9A-Z]{16}',
        'AD' => '[0-9]{8}[0-9A-Z]{12}',
        'AT' => '[0-9]{16}',
        'BE' => '[0-9]{12}',
        'BA' => '[0-9]{16}',
        'BG' => '[A-Z]{4}[0-9]{6}[0-9A-Z]{8}',
        'HR' => '[0-9]{17}',
        'CY' => '[0-9]{8}[0-9A-Z]{16}',
        'CZ' => '[0-9]{20}',
        'DK' => '[0-9]{14}',
        'EE' => '[0-9]{16}',
        'FO' => '[0-9]{14}',
        'FI' => '[0-9]{14}',
        'FR' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}',
        'PF' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Polynesia
        'TF' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Southern Territories
        'GP' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Guadeloupe
        'MQ' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Martinique
        'YT' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Mayotte
        'NC' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // New Caledonia
        'RE' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Reunion
        'BL' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Saint Barthelemy
        'MF' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // French Saint Martin
        'PM' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // Saint Pierre et Miquelon
        'WF' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}', // Wallis and Futuna Islands
        'GE' => '[0-9A-Z]{2}[0-9]{16}',
        'DE' => '[0-9]{18}',
        'GI' => '[A-Z]{4}[0-9A-Z]{15}',
        'GR' => '[0-9]{7}[0-9A-Z]{16}',
        'GL' => '[0-9]{14}',
        'HU' => '[0-9]{24}',
        'IS' => '[0-9]{22}',
        'IE' => '[0-9A-Z]{4}[0-9]{14}',
        'IL' => '[0-9]{19}',
        'IT' => '[A-Z][0-9]{10}[0-9A-Z]{12}',
        'KZ' => '[0-9]{3}[0-9A-Z]{3}[0-9]{10}',
        'KW' => '[A-Z]{4}[0-9]{22}',
        'LV' => '[A-Z]{4}[0-9A-Z]{13}',
        'LB' => '[0-9]{4}[0-9A-Z]{20}',
        'LI' => '[0-9]{5}[0-9A-Z]{12}',
        'LT' => '[0-9]{16}',
        'LU' => '[0-9]{3}[0-9A-Z]{13}',
        'MK' => '[0-9]{3}[0-9A-Z]{10}[0-9]{2}',
        'MT' => '[A-Z]{4}[0-9]{5}[0-9A-Z]{18}',
        'MR' => '[0-9]{23}',
        'MU' => '[A-Z]{4}[0-9]{19}[A-Z]{3}',
        'MC' => '[0-9]{10}[0-9A-Z]{11}[0-9]{2}',
        'ME' => '[0-9]{18}',
        'NL' => '[A-Z]{4}[0-9]{10}',
        'NO' => '[0-9]{11}',
        'PL' => '[0-9]{24}',
        'PT' => '[0-9]{21}',
        'RO' => '[A-Z]{4}[0-9A-Z]{16}',
        'SM' => '[A-Z][0-9]{10}[0-9A-Z]{12}',
        'SA' => '[0-9]{2}[0-9A-Z]{18}',
        'RS' => '[0-9]{18}',
        'SK' => '[0-9]{20}',
        'SI' => '[0-9]{15}',
        'ES' => '[0-9]{20}',
        'SE' => '[0-9]{20}',
        'CH' => '[0-9]{5}[0-9A-Z]{12}',
        'TN' => '[0-9]{20}',
        'TR' => '[0-9]{5}[0-9A-Z]{17}',
        'AE' => '[0-9]{19}',
        'GB' => '[A-Z]{4}[0-9]{14}',
        'CI' => '[0-9A-Z]{2}[0-9]{22}',
    ];

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        $value = $this->sanitize($value);

        if (mb_strlen($value) < 15) {
            return false;
        }

        $country = substr($value, 0, 2);
        if (!isset($this->patterns[$country])) {
            return false;
        }

        $check = substr($value, 4);
        if (!preg_match('/^' . $this->patterns[$country] . '$/', $check)) {
            return false;
        }
        $check .= substr($value, 0, 4);
        $check = strtr(
            $check,
            [
                'A' => '10',
                'B' => '11',
                'C' => '12',
                'D' => '13',
                'E' => '14',
                'F' => '15',
                'G' => '16',
                'H' => '17',
                'I' => '18',
                'J' => '19',
                'K' => '20',
                'L' => '21',
                'M' => '22',
                'N' => '23',
                'O' => '24',
                'P' => '25',
                'Q' => '26',
                'R' => '27',
                'S' => '28',
                'T' => '29',
                'U' => '30',
                'V' => '31',
                'W' => '32',
                'X' => '33',
                'Y' => '34',
                'Z' => '35'
            ]
        );

        return bcmod($check, 97) === '1';
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid IBAN.', $value);
    }

    /**
     * Ensures the IBAN number has the correct values by removing those not required (IBAN prefix) and not valid.
     *
     * @param string $value
     *
     * @return string
     */
    protected function sanitize(string $value): string
    {
        $value = ltrim(strtoupper($value));
        $value = preg_replace('/^I?IBAN/', '', $value);

        return preg_replace('/[^a-zA-Z0-9]/', '', $value);
    }
}
