<?php
namespace Altair\tests\Structure\Map;

trait hasKey
{
    public function hasKeyDataProvider()
    {
        // initial, key, has
        return [
            [[],            'a',    false],
            [['a' => 1],    'a',    true],
            [['a' => 1],    'b',    false],
        ];
    }

    /**
     * @dataProvider hasKeyDataProvider
     */
    public function testHasKey(array $initial, $key, bool $has)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($has, $instance->hasKey($key));
    }

    public function testHasKeyAfterRemoveAndPut()
    {
        $instance = $this->getInstance(['a' => 1]);
        $this->assertTrue($instance->hasKey('a'));

        $instance->remove('a');
        $this->assertFalse($instance->hasKey('a'));

        $instance->put('a', 1);
        $this->assertTrue($instance->hasKey('a'));
    }
}
