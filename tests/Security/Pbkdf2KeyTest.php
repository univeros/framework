<?php
namespace Altair\Tests\Security;


use Altair\Security\Support\Pbkdf2Key;
use PHPUnit\Framework\TestCase;

class Pbkdf2KeyTest extends TestCase
{
    public function dataProvider()
    {
        return [
            [
                'password',
                'salt',
                1,
                20,
                '120fb6cffcf8b32c43e7225256c4f837a86548c9'
            ],
            [
                "pass\0word",
                "sa\0lt",
                4096,
                32,
                '89b69d0516f829893c696226650a86878c029ac13ee276509d5ae58b6466a724'
            ],
            [
                'passwordPASSWORDpassword',
                'saltSALTsaltSALTsaltSALTsaltSALTsalt',
                4096,
                40,
                '348c89dbcbd32b2f32d814b8116e84cf2b17347ebc1800181c4e2a1fb8dd53e1c635518c7dac47e9'
            ],
        ];
    }

    /**
     * @dataProvider dataProvider
     *
     * @param string $password
     * @param string $salt
     * @param int $iterations
     * @param int $length
     * @param string $okm
     */
    public function testPbkdf2($password, $salt, $iterations, $length, $okm)
    {
        $derivedKey = (new Pbkdf2Key($password, $salt, $length, $iterations))->derive();
        $this->assertEquals($okm, bin2hex($derivedKey));
    }
}
