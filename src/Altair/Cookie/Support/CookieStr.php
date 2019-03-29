<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Support;

class CookieStr
{
    /**
     * Splits cookie string by the cookie delimiter.
     *
     * @param string $value
     *
     * @return array
     */
    public function split(string $value): array
    {
        return array_filter(preg_split('@\s*[;]\s*@', $value));
    }

    /**
     * Returns the cookie name and cookie value as an array where the name is the first element of the array and the
     * value the second -ie [cookie-name, cookie-value]
     *
     * @param string $value the cookie representation string
     *
     * @return array
     */
    public function splitPair(string $value): array
    {
        $pairParts = explode('=', $value, 2);
        if (count($pairParts) === 1) {
            $pairParts[1] = '';
        }

        return array_map(
            function ($part) {
                return urldecode($part);
            },
            $pairParts
        );
    }
}
