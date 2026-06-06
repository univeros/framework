<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Contracts\CookieInterface;
use Altair\Cookie\Cookie;
use Altair\Cookie\CookieManager;
use Altair\Cookie\Factory\SetCookieFactory;
use Altair\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;

use PHPUnit\Framework\Attributes\DataProvider;
class CookieManagerTest extends TestCase
{
    /**
     * @param $cookieString
     *
     */
    #[DataProvider('provideCookieStringAndExpectedCookiesData')]
    public function testGetCookiesFromRequest(string $cookieString, array $names): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn($cookieString);
        $manager = new CookieManager();

        foreach ($names as $name) {
            $cookie = $manager->getFromRequest($request, $name);
            $this->assertInstanceOf(CookieInterface::class, $cookie);
        }
    }

    public function testDefaultCookies(): void
    {
        $manager = new CookieManager();
        $request = $this->createStub(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn('');

        $cookie = $manager->getFromRequest($request, 'name', 'value');

        $this->assertEquals('name', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
    }

    public function testSetOnModifyOnAndRemoveFromRequest(): void
    {
        $cookie = new Cookie('name', 'value');
        $manager = new CookieManager();
        $request = $manager->setOnRequest(new ServerRequest(), $cookie);

        $this->assertEquals($cookie, $manager->getFromRequest($request, $cookie->getName()));

        $request = $manager->modifyOnRequest(
            $request,
            'name',
            static fn(Cookie $cookie): Cookie => $cookie->withValue('another value')
        );

        $this->assertEquals('another value', $manager->getFromRequest($request, 'name')->getValue());

        $request = $manager->removeFromRequest($request, 'name');

        $cookie = $manager->getFromRequest($request, 'name', 'different value');

        $this->assertNotEquals('another value', $cookie->getValue());
        $this->assertEquals('different value', $cookie->getValue());
    }

    public function testSetOnExpireOnModifyOnAndRemoveFromResponse(): void
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
            static fn(SetCookie $cookie): SetCookie => $cookie
                ->remember()
                ->withValue('another value')
        );
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertEquals('another value', $setCookie->getValue());

        $response = $manager->removeFromResponse($response, 'name');
        $setCookie = $manager->getFromResponse($response, 'name');
        $this->assertNull($setCookie->getValue());
    }

    #[DataProvider('setCookieStringsAndExpectedSetCookiesDataProvider')]
    public function testGetSetCookiesFromResponse(array $setCookieStrings, array $names, array $expectedSetCookies): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getHeader')->willReturn($setCookieStrings);
        $manager = new CookieManager();

        foreach ($names as $idx => $name) {
            $setCookie = $manager->getFromResponse($response, $name);
            $this->assertEquals($expectedSetCookies[$idx], $setCookie);
        }
    }

    public static function provideCookieStringAndExpectedCookiesData(): array
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

    public static function setCookieStringsAndExpectedSetCookiesDataProvider(): array
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
