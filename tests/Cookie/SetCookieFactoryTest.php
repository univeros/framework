<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\Factory\SetCookieFactory;
use Altair\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class SetCookieFactoryTest extends TestCase
{
    public function testCreate()
    {
        $setCookie = SetCookieFactory::create('name', 'value');

        $this->assertTrue($setCookie instanceof SetCookie);
        $this->assertEquals('name', $setCookie->getName());
        $this->assertEquals('value', $setCookie->getValue());
    }

    public function testCreateRemembered()
    {
        $setCookie = SetCookieFactory::createRemembered('name', 'value');

        $this->assertTrue($setCookie instanceof SetCookie);
        $fourYearsFromNow = (new \DateTime('+4 years'))->getTimestamp();
        $this->assertGreaterThan($fourYearsFromNow, $setCookie->getExpires());
    }

    public function testCreateExpired()
    {
        $setCookie = SetCookieFactory::createExpired('name');

        $this->assertLessThan(time(), $setCookie->getExpires());
    }

    /**
     * @param array $setCookieStrings
     * @param SetCookie[] $expectedSetCookies
     *
     * @dataProvider  setCookieStringsAndExpectedSetCookiesDataProvider
     */
    public function testParsingFromCookieStrings(array $setCookieStrings, array $expectedSetCookies)
    {
        $setCookies = SetCookieFactory::createCollectionFromCookieStrings($setCookieStrings);

        $this->assertCount(count($expectedSetCookies), $setCookies);

        foreach ($expectedSetCookies as $expectedSetCookie) {
            $setCookies->hasKey($expectedSetCookie->getName());
            $setCookie = $setCookies->get($expectedSetCookie->getName());
            $this->assertEquals($expectedSetCookie, $setCookie);
        }
    }

    /**
     * @param array $setCookieStrings
     * @param array $expectedSetCookies
     *
     * @dataProvider  setCookieStringsAndExpectedSetCookiesDataProvider
     */
    public function testCreatesFromResponse(array $setCookieStrings, array $expectedSetCookies)
    {
        $response = $this->prophesize(ResponseInterface::class);
        $response->getHeader(SetCookieInterface::HEADER)->willReturn($setCookieStrings);

        $setCookies = SetCookieFactory::createCollectionFromResponse($response->reveal());

        $this->assertCount(count($expectedSetCookies), $setCookies);

        foreach ($expectedSetCookies as $expectedSetCookie) {
            $setCookies->hasKey($expectedSetCookie->getName());
            $setCookie = $setCookies->get($expectedSetCookie->getName());
            $this->assertEquals($expectedSetCookie, $setCookie);
        }
    }

    /**
     * @param $cookieString
     * @param SetCookie $expectedSetCookie
     *
     * @dataProvider setCookieStringAndExpectedSetCookieDataProvider
     */
    public function testParsingFromCookieString($cookieString, SetCookie $expectedSetCookie)
    {
        $setCookie = SetCookieFactory::createFromCookieString($cookieString);

        $this->assertEquals($expectedSetCookie, $setCookie);
    }

    public function setCookieStringAndExpectedSetCookieDataProvider()
    {
        return [
            [
                'someCookie=',
                SetCookieFactory::create('someCookie'),
            ],
            [
                'LSID=DQAAAK%2FEaem_vYg; Path=/accounts; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly',
                SetCookieFactory::create('LSID')
                    ->withValue('DQAAAK/Eaem_vYg')
                    ->withPath('/accounts')
                    ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                    ->withSecure(true)
                    ->withHttpOnly(true),
            ],
        ];
    }

    public function setCookieStringsAndExpectedSetCookiesDataProvider()
    {
        return [
            [
                [],
                [],
            ],
            [
                [
                    'someCookie=',
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
                    SetCookieFactory::create('someCookie', 'someValue'),
                    SetCookieFactory::create('LSID')
                        ->withValue('DQAAAK/Eaem_vYg')
                        ->withPath('/accounts')
                        ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                        ->withSecure(true)
                        ->withHttpOnly(true),
                ],
            ],
            [
                [
                    'a=AAA',
                    'b=BBB',
                    'c=CCC',
                ],
                [
                    SetCookieFactory::create('a', 'AAA'),
                    SetCookieFactory::create('b', 'BBB'),
                    SetCookieFactory::create('c', 'CCC'),
                ],
            ],
        ];
    }
}
