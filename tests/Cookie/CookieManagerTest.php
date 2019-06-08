<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\Cookie;
use Altair\Cookie\CookieManager;
use Altair\Cookie\Factory\SetCookieFactory;
use Altair\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CookieManagerTest extends TestCase
{
    /**
     * @param $cookieString
     * @param array $names
     *
     * @dataProvider provideCookieStringAndExpectedCookiesData
     */
    public function testGetCookiesFromRequest($cookieString, array $names)
    {
        $request = $this->prophesize(RequestInterface::class);
        $request->getHeaderLine(Cookie::HEADER)->willReturn($cookieString);
        $request = $request->reveal();
        $manager = new CookieManager();

        foreach ($names as $name) {
            $cookie = $manager->getFromRequest($request, $name);
            $this->assertInstanceOf(CookieInterface::class, $cookie);
        }
    }

    public function testDefaultCookies()
    {
        $manager = new CookieManager();
        $request = $this->prophesize(RequestInterface::class);
        $request->getHeaderLine(Cookie::HEADER)->willReturn('');

        $cookie = $manager->getFromRequest($request->reveal(), 'name', 'value');

        $this->assertEquals('name', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
    }

    public function testSetOnModifyOnAndRemoveFromRequest()
    {
        $cookie = new Cookie('name', 'value');
        $manager = new CookieManager();
        $request = $manager->setOnRequest(new ServerRequest(), $cookie);

        $this->assertEquals($cookie, $manager->getFromRequest($request, $cookie->getName()));

        $request = $manager->modifyOnRequest(
            $request,
            'name',
            static function (Cookie $cookie) {
                return $cookie->withValue('another value');
            }
        );

        $this->assertEquals('another value', $manager->getFromRequest($request, 'name')->getValue());

        $request = $manager->removeFromRequest($request, 'name');

        $cookie = $manager->getFromRequest($request, 'name', 'different value');

        $this->assertNotEquals('another value', $cookie->getValue());
        $this->assertEquals('different value', $cookie->getValue());
    }

    public function testSetOnExpireOnModifyOnAndRemoveFromResponse()
    {
        $originalSetCookie = new SetCookie('name', 'value');
        $response = new Response();
        $manager = new CookieManager();

        $response = $manager->setOnResponse($response, $originalSetCookie);
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertEquals($originalSetCookie, $setCookie);

        $response = $manager->expireOnResponse($response, 'name');
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertLessThan(time(), $setCookie->getExpires());

        $response = $manager->modifyOnResponse(
            $response,
            'name',
            static function (SetCookie $cookie) {
                return $cookie
                    ->remember()
                    ->withValue('another value');
            }
        );
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertEquals('another value', $setCookie->getValue());

        $response = $manager->removeFromResponse($response, 'name');
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertNull($setCookie->getValue());
    }

    /**
     * @param array $setCookieStrings
     * @param array $names
     * @param array $expectedSetCookies
     *
     * @dataProvider  setCookieStringsAndExpectedSetCookiesDataProvider
     */
    public function testGetSetCookiesFromResponse(array $setCookieStrings, array $names, array $expectedSetCookies)
    {
        $response = $this->prophesize(ResponseInterface::class);
        $response->getHeader(SetCookieInterface::HEADER)->willReturn($setCookieStrings);
        $manager = new CookieManager();
        $response = $response->reveal();

        foreach ($names as $idx => $name) {
            $setCookie = $manager->getFromResponse($response, $name);
            $this->assertEquals($expectedSetCookies[$idx], $setCookie);
        }
    }

    public function provideCookieStringAndExpectedCookiesData()
    {
        return [
            [
                'theme=light',
                ['theme']
            ],
            [
                'theme=light; sessionToken=abc123',
                [
                    'theme',
                    'sessionToken'
                ]
            ]
        ];
    }

    public function setCookieStringsAndExpectedSetCookiesDataProvider()
    {
        return [
            [
                [
                    'someCookie=',
                ],
                [
                    'someCookie'
                ],
                [
                    SetCookieFactory::create('someCookie'),
                ],
            ],
            [
                [
                    'someCookie=someValue',
                    'LSID=DQAAAK%2FEaem_vYg; Path=/accounts; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly',
                ],
                [
                    'someCookie',
                    'LSID'
                ],
                [
                    SetCookieFactory::create('someCookie', 'someValue'),
                    SetCookieFactory::create('LSID')
                        ->withValue('DQAAAK/Eaem_vYg')
                        ->withPath('/accounts')
                        ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                        ->withSecure(true)
                        ->withHttpOnly(true),
                ],
            ],
        ];
    }
}
