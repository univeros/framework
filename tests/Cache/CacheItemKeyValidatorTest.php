<?php

namespace Altair\Tests\Cache;

use Altair\Cache\Validator\CacheItemKeyValidator;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
class CacheItemKeyValidatorTest extends TestCase
{
    private CacheItemKeyValidator $validator;

    #[\Override]
    protected function setUp(): void    {
        $this->validator = new CacheItemKeyValidator();
    }

    /**
     * @param mixed $key
     */
    #[DataProvider('provideInvalidKey')]
    public function testInvalidKey(string|bool|int|float|\Exception|array|null $key): void
    {
        $this->assertFalse($this->validator->validate($key));
    }

    /**
     * @param mixed $key
     */
    #[DataProvider('provideValidKey')]
    public function testValiddKey(string $key): void
    {
        $this->assertTrue($this->validator->validate($key));
    }

    public static function provideInvalidKey(): array
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

    public static function provideValidKey(): array
    {
        return [
            ['one'],
            ['two1'],
            ['true'],
            ['false']
        ];
    }
}
