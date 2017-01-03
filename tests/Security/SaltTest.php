<?php
namespace Altair\Tests\Security;

use Altair\Security\Support\Salt;
use PHPUnit\Framework\TestCase;

class SaltTest extends TestCase
{
    public function testLength()
    {
        $salt = new Salt();

        $this->assertEquals(12, strlen($salt->generate(12)));
        $this->assertEquals(36, strlen($salt->generate(36)));
    }
}
