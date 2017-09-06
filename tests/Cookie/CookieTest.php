<?php

namespace Altair\Tests\Cookie;

use Altair\Cookie\Cookie;
use PHPUnit\Framework\TestCase;

class CookieTest extends TestCase
{
    public function testCookieCreation()
    {
        $cookie = new Cookie('name');
        $this->assertEquals('name', $cookie->getName());
        $this->assertNull($cookie->getValue());

        $this->assertEquals('value', $cookie->withValue('value')->getValue());
    }

    public function testImmutability()
    {
        $cookie = new Cookie('name', 'value');
        $newCookie = $cookie->withValue('another-value');

        $this->assertNotEquals($cookie, $newCookie);
    }

    public function testToString()
    {
        $cookie = new Cookie('name', 'value');

        $this->assertEquals('name=value', (string)$cookie);
    }
}
