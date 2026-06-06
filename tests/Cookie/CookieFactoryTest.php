<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Cookie;
use Altair\Cookie\Factory\CookieFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use PHPUnit\Framework\Attributes\DataProvider;
class CookieFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $cookie = CookieFactory::create('name', 'value');

        $this->assertTrue($cookie instanceof Cookie);
        $this->assertEquals('name', $cookie->getName());
        $this->assertEquals('value', $cookie->getValue());
    }

    #[DataProvider('parsingFromCookieStringDataProvider')]
    public function testParsingFromCookieString(string $cookieString, string $expectedName, string $expectedValue): void
    {
        $cookie = CookieFactory::createFromPairString($cookieString);

        $this->assertEquals($expectedName, $cookie->getName());
        $this->assertEquals($expectedValue, $cookie->getValue());
    }

    #[DataProvider('createCollectionFromCookieStringDataProvider')]
    public function testCreateCollectionFromCookieString(string $cookieString, array $expectedPairs): void
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

    #[DataProvider('provideCookieStringAndExpectedCookiesData')]
    public function testCreateCollectionFromRequest(string $cookieString, array $expectedCookies): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getHeaderLine')->willReturn($cookieString);

        $cookieCollection = CookieFactory::createCollectionFromRequest($request);

        foreach ($expectedCookies as $expectedCookie) {
            $cookie = $cookieCollection->get($expectedCookie->getName());

            $this->assertTrue($cookie instanceof Cookie);
            $this->assertEquals($cookie->getName(), $expectedCookie->getName());
            $this->assertEquals($cookie->getValue(), $expectedCookie->getValue());
        }
    }

    public static function parsingFromCookieStringDataProvider(): array
    {
        return [
            ['someCookie=something', 'someCookie', 'something'],
            ['hello%3Dworld=how%22are%27you', 'hello=world', 'how"are\'you'],
            ['empty=', 'empty', ''],
        ];
    }

    public static function createCollectionFromCookieStringDataProvider(): array
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

    public static function provideCookieStringAndExpectedCookiesData(): array
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
