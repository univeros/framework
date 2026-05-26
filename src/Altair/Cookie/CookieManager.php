<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie;

use Altair\Cookie\Factory\CookieFactory;
use Altair\Cookie\Factory\SetCookieFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CookieManager
{
    /**
     * @param string|null $value
     *
     */
    public function getFromRequest(RequestInterface $request, string $name, string $value = null): Cookie
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);

        return $cookies->hasKey($name)
            ? $cookies->get($name)
            : CookieFactory::create($name, $value);
    }

    public function setOnRequest(RequestInterface $request, Cookie $cookie): RequestInterface
    {
        return CookieFactory::createCollectionFromRequest($request)
            ->putCookie($cookie)
            ->injectIntoRequestHeader($request);
    }

    public function modifyOnRequest(RequestInterface $request, string $name, callable $modify): RequestInterface
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);
        $cookie = $modify($cookies->hasKey($name) ? $cookies->get($name) : CookieFactory::create($name));

        return $this->setOnRequest($request, $cookie);
    }

    /**
     * @param $name
     *
     */
    public function removeFromRequest(RequestInterface $request, $name): RequestInterface
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);
        $cookies->remove($name);

        return $cookies->injectIntoRequestHeader($request);
    }

    /**
     * @param string|null $value
     *
     */
    public function getFromResponse(ResponseInterface $response, string $name, string $value = null): SetCookie
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);

        return $cookies->hasKey($name)
            ? $cookies->get($name)
            : SetCookieFactory::create($name, $value);
    }

    public function setOnResponse(ResponseInterface $response, SetCookie $cookie): ResponseInterface
    {
        return SetCookieFactory::createCollectionFromResponse($response)
            ->putSetCookie($cookie)
            ->injectIntoResponseHeader($response);
    }

    public function expireOnResponse(ResponseInterface $response, string $name): ResponseInterface
    {
        return $this->setOnResponse($response, SetCookieFactory::createExpired($name));
    }

    public function modifyOnResponse(ResponseInterface $response, string $name, callable $modify): ResponseInterface
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);
        $cookie = $modify($cookies->hasKey($name) ? $cookies->get($name) : SetCookieFactory::create($name));

        return $cookies->putSetCookie($cookie)->injectIntoResponseHeader($response);
    }

    /**
     * @param $name
     *
     */
    public function removeFromResponse(ResponseInterface $response, $name): ResponseInterface
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);
        $cookies->remove($name);

        return $cookies->injectIntoResponseHeader($response);
    }
}
