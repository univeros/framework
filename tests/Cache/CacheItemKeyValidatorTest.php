<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Validator\CacheItemKeyValidator;
use PHPUnit\Framework\TestCase;

class CacheItemKeyValidatorTest extends TestCase
{
    /**
     * @var CacheItemKeyValidator
     */
    private $validator;

    protected function setUp()
    {
        $this->validator = new CacheItemKeyValidator();
    }

    /**
     * @dataProvider provideInvalidKey
     * @param mixed $key
     */
    public function testInvalidKey($key)
    {
        $this->assertFalse($this->validator->validate($key));
    }

    /**
     * @dataProvider provideValidKey
     * @param mixed $key
     */
    public function testValiddKey($key)
    {
        $this->assertTrue($this->validator->validate($key));
    }

    public function provideInvalidKey()
    {
        return [
            [''],
            ['{'],
            ['}'],
            ['('],
            [')'],
            ['/'],
            ['\\'],
            ['@'],
            [':'],
            [true],
            [null],
            [1],
            [1.1],
            [[[]]],
            [new \Exception('foo')],
        ];
    }

    public function provideValidKey()
    {
        return [
            ['one'],
            ['two1'],
            ['true'],
            ['false']
        ];
    }
}
