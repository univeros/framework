<?php
namespace Altair\Tests\Structure\Map;

trait get
{
    public static function getDataProvider(): array
    {
        // initial, key, expected
        return [
            [['a' => 1], 'a', 1],
        ];
    }

    /**
     * @dataProvider getDataProvider
     */
    public function testGet(array $initial, mixed $key, mixed $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->get($key));
    }

    public function testGetDefault(): void
    {
        $instance = static::getInstance();
        $this->assertEquals('a', $instance->get('?', 'a'));
    }

    public function testGetKeyNotFoundIsNull(): void
    {
        $instance = static::getInstance();
        $this->assertEquals(null, $instance->get('?'));
    }

    public function testArrayAccessGet(): void
    {
        $instance = static::getInstance(['a' => 1]);
        $this->assertEquals(1, $instance['a']);
    }

    public function testArrayAccessGetByReference(): void
    {
        $instance = static::getInstance(['a' => [1]]);
        $this->assertEquals(1, $instance['a'][0]);
    }

    public function testArrayAccessGetKeyNotFound(): void
    {
        $instance = static::getInstance(['a' => 1]);
        $this->expectKeyNotFoundException();
        $instance['b'];
    }
}
