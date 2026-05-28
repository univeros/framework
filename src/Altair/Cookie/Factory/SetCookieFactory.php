<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Factory;

use Altair\Cookie\Collection\SetCookieCollection;
use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\SetCookie;
use Altair\Cookie\Support\CookieStr;
use Exception;
use Psr\Http\Message\ResponseInterface;

class SetCookieFactory
{
    public static function create(string $name, ?string $value = null): SetCookie
    {
        return new SetCookie($name, $value);
    }

    /**
     *@throws Exception
     */
    public static function createRemembered(string $name, ?string $value = null): SetCookie
    {
        return (new SetCookie($name, $value))->remember();
    }

    /**
     *@throws Exception
     */
    public static function createExpired(string $name): SetCookie
    {
        return (new SetCookie($name))->expire();
    }

    public static function createFromCookieString(string $string): SetCookie
    {
        $cookieStr = new CookieStr();
        $attributes = $cookieStr->split($string);
        [$name, $value] = $cookieStr->splitPair(array_shift($attributes) ?? '');
        $cookie = new SetCookie($name, $value);

        while ($attribute = array_shift($attributes)) {
            $pair = explode('=', (string) $attribute, 2);
            $key = strtolower($pair[0]);
            $value = $pair[1] ?? null;

            if ('secure' === $key) {
                $cookie = $cookie->withSecure(true);
                continue;
            }

            if ('httponly' === $key) {
                $cookie = $cookie->withHttpOnly(true);
                continue;
            }

            if (null === $value) {
                continue;
            }

            switch ($key) {
                case 'expires':
                    $cookie = $cookie->withExpires($value);
                    break;
                case 'max-age':
                    $cookie = $cookie->withMaxAge((int) $value);
                    break;
                case 'domain':
                    $cookie = $cookie->withDomain($value);
                    break;
                case 'path':
                    $cookie = $cookie->withPath($value);
                    break;
            }
        }

        return $cookie;
    }

    /**
     * @param list<string> $strings
     */
    public static function createCollectionFromCookieStrings(array $strings): SetCookieCollection
    {
        return new SetCookieCollection(
            array_map(
                static::createFromCookieString(...),
                $strings
            )
        );
    }

    public static function createCollectionFromResponse(ResponseInterface $response): SetCookieCollection
    {
        return new SetCookieCollection(
            array_map(
                static::createFromCookieString(...),
                $response->getHeader(SetCookieInterface::HEADER)
            )
        );
    }
}
