<?php
namespace Altair\Tests\Structure\Map;

trait get
{
    public static function getDataProvider()
    {
        // initial, key, expected
        return [
            [['a' => 1], 'a', 1],
        ];
    }

    /**
     * @dataProvider getDataProvider
     * @param mixed $key
     * @param mixed $expected
     */
    public function testGet(array $initial, $key, $expected)
    {
        $instance = static::getInstance($initial);
        $this->assertEquals($expected, $instance->get($key));
    }

    public function testGetDefault()
    {
        $instance = static::getInstance();
        $this->assertEquals('a', $instance->get('?', 'a'));
    }

    public function testGetKeyNotFoundIsNull()
    {
        $instance = static::getInstance();
        $this->assertEquals(null, $instance->get('?'));
    }

    public function testArrayAccessGet()
    {
        $instance = static::getInstance(['a' => 1]);
        $this->assertEquals(1, $instance['a']);
    }

    public function testArrayAccessGetByReference()
    {
        $instance = static::getInstance(['a' => [1]]);
        $this->assertEquals(1, $instance['a'][0]);
    }

    public function testArrayAccessGetKeyNotFound()
    {
        $instance = static::getInstance(['a' => 1]);
        $this->expectKeyNotFoundException();
        $instance['b'];
    }
}
