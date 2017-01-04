<?php
namespace Altair\Cookie\Factory;

use Altair\Cookie\Collection\SetCookieCollection;
use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\SetCookie;
use Altair\Cookie\Support\CookieStr;
use Psr\Http\Message\ResponseInterface;

class SetCookieFactory
{
    /**
     * @param string $name
     * @param string|null $value
     *
     * @return SetCookie
     */
    public static function create(string $name, string $value = null): SetCookie
    {
        return new SetCookie($name, $value);
    }

    /**
     * @param string $name
     * @param string|null $value
     *
     * @return SetCookie
     */
    public static function createRemembered(string $name, string $value = null): SetCookie
    {
        return (new SetCookie($name, $value))->remember();
    }

    /**
     * @param string $name
     *
     * @return SetCookie
     */
    public static function createExpired(string $name): SetCookie
    {
        return (new SetCookie($name))->expire();
    }

    /**
     * @param string $string
     *
     * @return SetCookie
     */
    public static function createFromCookieString(string $string): SetCookie
    {
        $cookieStr = new CookieStr();
        $attributes = $cookieStr->split($string);
        list($name, $value) = $cookieStr->splitPair(array_shift($attributes));
        $cookie = new SetCookie($name, $value);

        while ($attribute = array_shift($attributes)) {
            $pair = explode('=', $attribute, 2);
            $key = strtolower($pair[0]);
            $value = $pair[1]?? null;

            if ($key === 'secure') {
                $cookie = $cookie->withSecure(true);
                continue;
            } elseif ($key === 'httponly') {
                $cookie = $cookie->withHttpOnly(true);
                continue;
            } elseif ($value === null) {
                continue;
            }

            switch ($key) {
                case 'expires':
                    $cookie = $cookie->withExpires($value);
                    break;
                case 'max-age':
                    $cookie = $cookie->withMaxAge($value);
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
     * @param array $strings
     *
     * @return SetCookieCollection
     */
    public static function createCollectionFromCookieStrings(array $strings): SetCookieCollection
    {
        return new SetCookieCollection(
            array_map(
                function ($string) {
                    return static::createFromCookieString($string);
                },
                $strings
            )
        );
    }

    /**
     * @param ResponseInterface $response
     *
     * @return SetCookieCollection
     */
    public static function createCollectionFromResponse(ResponseInterface $response): SetCookieCollection
    {
        return new SetCookieCollection(
            array_map(
                function ($string) {
                    return static::createFromCookieString($string);
                },
                $response->getHeader(SetCookieInterface::HEADER)
            )
        );
    }
}
