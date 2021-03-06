<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Factory;

use Altair\Cookie\Collection\CookieCollection;
use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Cookie;
use Altair\Cookie\Support\CookieStr;
use Psr\Http\Message\RequestInterface;

class CookieFactory
{
    /**
     * @param string $name
     * @param string|null $value
     *
     * @return Cookie
     */
    public static function create(string $name, string $value = null): Cookie
    {
        return new Cookie($name, $value);
    }

    /**
     * @param string $pair
     *
     * @return Cookie
     */
    public static function createFromPairString(string $pair): Cookie
    {
        [$name, $value] = (new CookieStr())->splitPair($pair);

        $cookie = new Cookie($name);

        return $value !== null ? $cookie->withValue($value) : $cookie;
    }

    /**
     * @param string $string
     *
     * @return CookieCollection
     */
    public static function createCollectionFromCookieString(string $string): CookieCollection
    {
        $pairs = (new CookieStr())->split($string);

        return new CookieCollection(
            array_map(
                static function ($pair) {
                    return static::createFromPairString($pair);
                },
                $pairs
            )
        );
    }

    /**
     * @param RequestInterface $request
     *
     * @return CookieCollection
     */
    public static function createCollectionFromRequest(RequestInterface $request): CookieCollection
    {
        $string = $request->getHeaderLine(CookieInterface::HEADER);
        return static::createCollectionFromCookieString($string);
    }
}
