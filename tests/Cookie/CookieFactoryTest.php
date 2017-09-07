<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Cookie;
use Altair\Cookie\Factory\CookieFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class CookieFactoryTest extends TestCase
{
    public function testCreate()
    {
        $cookie = CookieFactory::create('name', 'value');

        $this->assertTrue($cookie instanceof Cookie);
        $this->assertEquals('name', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
    }

    /**
     * @param string $cookieString
     * @param string $expectedName
     * @param string $expectedValue
     *
     * @dataProvider  parsingFromCookieStringDataProvider
     */
    public function testParsingFromCookieString($cookieString, $expectedName, $expectedValue)
    {
        $cookie = CookieFactory::createFromPairString($cookieString);

        $this->assertEquals($expectedName, $cookie->getName());
        $this->assertEquals($expectedValue, $cookie->getValue());
    }

    /**
     * @param string $cookieString
     * @param array $expectedPairs
     *
     * @dataProvider createCollectionFromCookieStringDataProvider
     */
    public function testCreateCollectionFromCookieString($cookieString, array $expectedPairs)
    {
        $cookies = CookieFactory::createCollectionFromCookieString($cookieString);

        $this->assertCount(count($expectedPairs), $cookies);

        /** @var Cookie $cookie */
        $cnt = 0;
        foreach ($cookies->getIterator() as $cookie) {
            [$name, $value] = $expectedPairs[$cnt++];
            $this->assertEquals($name, $cookie->getName());
            $this->assertEquals($value, $cookie->getValue());
        }
    }

    /**
     * @param string $cookieString
     * @param array $expectedCookies
     *
     * @dataProvider  provideCookieStringAndExpectedCookiesData
     */
    public function testCreateCollectionFromRequest($cookieString, array $expectedCookies)
    {
        $request = $this->prophesize(RequestInterface::class);
        $request->getHeaderLine(Cookie::HEADER)->willReturn($cookieString);

        $cookieCollection = CookieFactory::createCollectionFromRequest($request->reveal());

        foreach ($expectedCookies as $expectedCookie) {
            $cookie = $cookieCollection->get($expectedCookie->getName());

            $this->assertTrue($cookie instanceof Cookie);
            $this->assertEquals($cookie->getName(), $expectedCookie->getName());
            $this->assertEquals($cookie->getValue(), $expectedCookie->getValue());
        }
    }

    public function parsingFromCookieStringDataProvider()
    {
        return [
            ['someCookie=something', 'someCookie', 'something'],
            ['hello%3Dworld=how%22are%27you', 'hello=world', 'how"are\'you'],
            ['empty=', 'empty', ''],
        ];
    }

    public function createCollectionFromCookieStringDataProvider()
    {
        return [
            [
                'theme=light; sessionToken=abc123',
                [
                    ['theme', 'light'],
                    ['sessionToken', 'abc123'],
                ]
            ],
            [
                'theme=light; sessionToken=abc123;',
                [
                    ['theme', 'light'],
                    ['sessionToken', 'abc123'],
                ]
            ],
        ];
    }

    public function provideCookieStringAndExpectedCookiesData()
    {
        return [
            [
                'theme=light',
                [
                    CookieFactory::create('theme', 'light'),
                ]
            ],
            [
                'theme=light; sessionToken=abc123',
                [
                    CookieFactory::create('theme', 'light'),
                    CookieFactory::create('sessionToken', 'abc123'),
                ]
            ]
        ];
    }
}
