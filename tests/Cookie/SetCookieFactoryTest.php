<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Factory\SetCookieFactory;
use Altair\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use PHPUnit\Framework\Attributes\DataProvider;
class SetCookieFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $setCookie = SetCookieFactory::create('name', 'value');

        $this->assertTrue($setCookie instanceof SetCookie);
        $this->assertEquals('name', $setCookie->getName());
        $this->assertEquals('value', $setCookie->getValue());
    }

    public function testCreateRemembered(): void
    {
        $setCookie = SetCookieFactory::createRemembered('name', 'value');

        $this->assertTrue($setCookie instanceof SetCookie);
        $fourYearsFromNow = (new \DateTime('+4 years'))->getTimestamp();
        $this->assertGreaterThan($fourYearsFromNow, $setCookie->getExpires());
    }

    public function testCreateExpired(): void
    {
        $setCookie = SetCookieFactory::createExpired('name');

        $this->assertLessThan(time(), $setCookie->getExpires());
    }

    /**
     * @param SetCookie[] $expectedSetCookies
     */
    #[DataProvider('setCookieStringsAndExpectedSetCookiesDataProvider')]
    public function testParsingFromCookieStrings(array $setCookieStrings, array $expectedSetCookies): void
    {
        $setCookies = SetCookieFactory::createCollectionFromCookieStrings($setCookieStrings);

        $this->assertCount(count($expectedSetCookies), $setCookies);

        foreach ($expectedSetCookies as $expectedSetCookie) {
            $setCookies->hasKey($expectedSetCookie->getName());
            $setCookie = $setCookies->get($expectedSetCookie->getName());
            $this->assertEquals($expectedSetCookie, $setCookie);
        }
    }

    #[DataProvider('setCookieStringsAndExpectedSetCookiesDataProvider')]
    public function testCreatesFromResponse(array $setCookieStrings, array $expectedSetCookies): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getHeader')->willReturn($setCookieStrings);

        $setCookies = SetCookieFactory::createCollectionFromResponse($response);

        $this->assertCount(count($expectedSetCookies), $setCookies);

        foreach ($expectedSetCookies as $expectedSetCookie) {
            $setCookies->hasKey($expectedSetCookie->getName());
            $setCookie = $setCookies->get($expectedSetCookie->getName());
            $this->assertEquals($expectedSetCookie, $setCookie);
        }
    }

    /**
     * @param $cookieString
     *
     */
    #[DataProvider('setCookieStringAndExpectedSetCookieDataProvider')]
    public function testParsingFromCookieString(string $cookieString, SetCookie $expectedSetCookie): void
    {
        $setCookie = SetCookieFactory::createFromCookieString($cookieString);

        $this->assertEquals($expectedSetCookie, $setCookie);
    }

    public static function setCookieStringAndExpectedSetCookieDataProvider(): array
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

    public static function setCookieStringsAndExpectedSetCookiesDataProvider(): array
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
