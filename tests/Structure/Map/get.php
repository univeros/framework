<?php
namespace Altair\Tests\Structure\Map;

trait get
{
    public function getDataProvider()
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
        $instance = $this->getInstance($initial);
        $this->assertEquals($expected, $instance->get($key));
    }

    public function testGetDefault()
    {
        $instance = $this->getInstance();
        $this->assertEquals('a', $instance->get('?', 'a'));
    }

    public function testGetKeyNotFound()
    {
        $instance = $this->getInstance();
        $this->expectKeyNotFoundException();
        $instance->get('?');
    }

    public function testArrayAccessGet()
    {
        $instance = $this->getInstance(['a' => 1]);
        $this->assertEquals(1, $instance['a']);
    }

    public function testArrayAccessGetByReference()
    {
        $instance = $this->getInstance(['a' => [1]]);
        $this->assertEquals(1, $instance['a'][0]);
    }

    public function testArrayAccessGetKeyNotFound()
    {
        $instance = $this->getInstance(['a' => 1]);
        $this->expectKeyNotFoundException();
        $instance['b'];
    }
}
