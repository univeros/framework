<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Support\CookieStr;
use PHPUnit\Framework\TestCase;

class CookieStrTest extends TestCase
{
    /**
     * @param $string
     * @param $count
     *
     * @dataProvider  setCookieStringDataProvider
     */
    public function testSplit($string, $count)
    {
        $attributes = (new CookieStr())->split($string);

        $this->assertCount($count, $attributes);
    }

    public function testSplitPair()
    {
        $cookieStr = new CookieStr();

        $pair = $cookieStr->splitPair('name=');
        $this->assertEquals('name', $pair[0]);
        $this->assertEquals('', $pair[1]);

        $pair = $cookieStr->splitPair('new-name=value');
        $this->assertEquals('new-name', $pair[0]);
        $this->assertEquals('value', $pair[1]);
    }

    public function setCookieStringDataProvider()
    {
        return [
            [
                'name=',
                1,
            ],
            [
                'name=value',
                1
            ],
            [
                'LSID=DQAAAK%2FEaem_vYg; Path=/accounts; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure; HttpOnly',
                5
            ],
            [
                'HSID=AYQEVn%2F.DKrdst; Domain=.foo.com; Path=/; Expires=Wed, 13 Jan 2021 22:23:01 GMT',
                4
            ],
            [
                'SSID=Ap4P%2F.GTEq; Domain=foo.com; Path=/; Expires=Wed, 13 Jan 2021 22:23:01 GMT; Secure',
                5
            ],
            [
                'lu=Rg3vHJZnehYLjVg7qi3bZjzg; Domain=.example.com; Path=/',
                3
            ],
        ];
    }
}
