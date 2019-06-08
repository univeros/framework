<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Contracts\SetCookieInterface;
use Altair\Cookie\CookieManager;
use Altair\Cookie\Factory\SetCookieFactory;
use Altair\Cookie\SetCookie;
use PHPUnit\Framework\TestCase;
use Zend\Diactoros\Response;

class SetCookieTest extends TestCase
{
    public function testThatCanBeAddedToAResponse()
    {
        $setCookie = new SetCookie('name', 'value');
        $manager = new CookieManager();
        $response = $manager->setOnResponse(new Response(), $setCookie);

        $this->assertTrue($response->hasHeader(SetCookieInterface::HEADER));
        $this->assertEquals('name=value', $response->getHeader(SetCookieInterface::HEADER)[0]);

        $cookie = $manager->getFromResponse($response, 'name');
        $this->assertEquals($setCookie->getValue(), $cookie->getValue());
    }

    /**
     * @param string $setCookieString
     * @param SetCookieInterface $expectedSetCookie
     *
     * @dataProvider parsesFromSetCookieStringDataProvider
     */
    public function testParsesFromSetCookieString($setCookieString, SetCookieInterface $expectedSetCookie)
    {
        $setCookie = SetCookieFactory::createFromCookieString($setCookieString);

        $this->assertEquals($expectedSetCookie, $setCookie);
        $this->assertEquals($setCookieString, (string)$setCookie);
    }

    public function parsesFromSetCookieStringDataProvider()
    {
        return [
            [
                'name=',
                SetCookieFactory::create('name')
            ],
            [
                'name=value',
                SetCookieFactory::create('name', 'value')
            ],
            [
                'LSID=DQAAAK%2FEaem_vYg; Path=/accounts; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly',
                SetCookieFactory::create('LSID')
                    ->withValue('DQAAAK/Eaem_vYg')
                    ->withPath('/accounts')
                    ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
            [
                'HSID=AYQEVn%2F.DKrdst; Domain=.foo.com; Path=/; Expires=Wed, 13 Jan 2021 22:23:01 GMT; HttpOnly',
                SetCookieFactory::create('HSID')
                    ->withValue('AYQEVn/.DKrdst')
                    ->withDomain('.foo.com')
                    ->withPath('/')
                    ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                    ->withHttpOnly(true)
            ],
            [
                'SSID=Ap4P%2F.GTEq; Domain=foo.com; Path=/; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly',
                SetCookieFactory::create('SSID')
                    ->withValue('Ap4P/.GTEq')
                    ->withDomain('foo.com')
                    ->withPath('/')
                    ->withExpires('Wed, 13 Jan 2021 22:23:01 GMT')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/; Expires=Tue, 15 Jan 2013 21:47:38 GMT; HttpOnly',
                SetCookieFactory::create('lu')
                    ->withValue('Rg3vHJZnehYLjVg7qi3bZjzg')
                    ->withExpires('Tue, 15-Jan-2013 21:47:38 GMT')
                    ->withPath('/')
                    ->withDomain('.example.com')
                    ->withHttpOnly(true)
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/; Max-Age=500; Secure; HttpOnly',
                SetCookieFactory::create('lu')
                    ->withValue('Rg3vHJZnehYLjVg7qi3bZjzg')
                    ->withMaxAge(500)
                    ->withPath('/')
                    ->withDomain('.example.com')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/; Expires=Tue, 15 Jan 2013 21:47:38 GMT; Max-Age=500; Secure; HttpOnly',
                SetCookieFactory::create('lu')
                    ->withValue('Rg3vHJZnehYLjVg7qi3bZjzg')
                    ->withExpires('Tue, 15-Jan-2013 21:47:38 GMT')
                    ->withMaxAge(500)
                    ->withPath('/')
                    ->withDomain('.example.com')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/; Expires=Tue, 15 Jan 2013 21:47:38 GMT; Max-Age=500; Secure; HttpOnly',
                SetCookieFactory::create('lu')
                    ->withValue('Rg3vHJZnehYLjVg7qi3bZjzg')
                    ->withExpires(1358286458)
                    ->withMaxAge(500)
                    ->withPath('/')
                    ->withDomain('.example.com')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/; Expires=Tue, 15 Jan 2013 21:47:38 GMT; Max-Age=500; Secure; HttpOnly',
                SetCookieFactory::create('lu')
                    ->withValue('Rg3vHJZnehYLjVg7qi3bZjzg')
                    ->withExpires(new \DateTime('Tue, 15-Jan-2013 21:47:38 GMT'))
                    ->withMaxAge(500)
                    ->withPath('/')
                    ->withDomain('.example.com')
                    ->withSecure(true)
                    ->withHttpOnly(true)
            ],
        ];
    }

    public function testCreatesExpiredCookies()
    {
        $setCookie = SetCookieFactory::createExpired('name');

        $this->assertLessThan(time(), $setCookie->getExpires());
    }

    public function testItCreatesEverLastingCookies()
    {
        $setCookie = SetCookieFactory::createRemembered('name');

        $fourYearsFromNow = (new \DateTime('+4 years'))->getTimestamp();
        $this->assertGreaterThan($fourYearsFromNow, $setCookie->getExpires());
    }
}
