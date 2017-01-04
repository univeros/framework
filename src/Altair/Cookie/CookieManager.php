<?php
namespace Altair\Cookie;

use Altair\Cookie\Factory\CookieFactory;
use Altair\Cookie\Factory\SetCookieFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CookieManager
{
    /**
     * @param RequestInterface $request
     * @param string $name
     * @param string|null $value
     *
     * @return Cookie
     */
    public function getFromRequest(RequestInterface $request, string $name, string $value = null): Cookie
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);

        return $cookies->hasKey($name)
            ? $cookies->get($name)
            : CookieFactory::create($name, $value);
    }

    /**
     * @param RequestInterface $request
     * @param Cookie $cookie
     *
     * @return RequestInterface
     */
    public function setOnRequest(RequestInterface $request, Cookie $cookie): RequestInterface
    {
        return CookieFactory::createCollectionFromRequest($request)
            ->putCookie($cookie)
            ->injectIntoRequestHeader($request);
    }

    /**
     * @param RequestInterface $request
     * @param string $name
     * @param callable $modify
     *
     * @return RequestInterface
     */
    public function modifyOnRequest(RequestInterface $request, string $name, callable $modify): RequestInterface
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);
        $cookie = $modify($cookies->hasKey($name) ? $cookies->get($name) : CookieFactory::create($name));

        return $this->setOnRequest($request, $cookie);
    }

    /**
     * @param RequestInterface $request
     * @param $name
     *
     * @return RequestInterface
     */
    public function removeFromRequest(RequestInterface $request, $name): RequestInterface
    {
        $cookies = CookieFactory::createCollectionFromRequest($request);
        $cookies->remove($name);

        return $cookies->injectIntoRequestHeader($request);
    }

    /**
     * @param ResponseInterface $response
     * @param string $name
     * @param string|null $value
     *
     * @return SetCookie
     */
    public function getFromResponse(ResponseInterface $response, string $name, string $value = null): SetCookie
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);

        return $cookies->hasKey($name)
            ? $cookies->get($name)
            : SetCookieFactory::create($name, $value);
    }

    /**
     * @param ResponseInterface $response
     * @param SetCookie $cookie
     *
     * @return ResponseInterface
     */
    public function setOnResponse(ResponseInterface $response, SetCookie $cookie): ResponseInterface
    {
        return SetCookieFactory::createCollectionFromResponse($response)
            ->putSetCookie($cookie)
            ->injectIntoResponseHeader($response);
    }

    /**
     * @param ResponseInterface $response
     * @param string $name
     *
     * @return ResponseInterface
     */
    public function expireOnResponse(ResponseInterface $response, string $name): ResponseInterface
    {
        return static::setOnResponse($response, SetCookieFactory::createExpired($name));
    }

    /**
     * @param ResponseInterface $response
     * @param string $name
     * @param callable $modify
     *
     * @return ResponseInterface
     */
    public function modifyOnResponse(ResponseInterface $response, string $name, callable $modify)
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);
        $cookie = $modify($cookies->hasKey($name) ? $cookies->get($name) : SetCookieFactory::create($name));

        return $cookies->putSetCookie($cookie)->injectIntoResponseHeader($response);
    }

    /**
     * @param ResponseInterface $response
     * @param $name
     *
     * @return ResponseInterface
     */
    public function removeFromResponse(ResponseInterface $response, $name): ResponseInterface
    {
        $cookies = SetCookieFactory::createCollectionFromResponse($response);
        $cookies->remove($name);

        return $cookies->injectIntoResponseHeader($response);
    }
}
