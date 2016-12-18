<?php
namespace Altair\Tests\Structure\Map;

trait hasValue
{
    public function hasValueDataProvider()
    {
        // initial, value, expected
        return [
            [[],                1,      false],
            [['a' => 1],        1,      true],
            [['a' => 1],        2,      false],
            [['a' => null],     null,   true],
        ];
    }

    /**
     * @dataProvider hasValueDataProvider
     */
    public function testHasValue(array $initial, $value, bool $expected)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($expected, $instance->hasValue($value));
    }

    public function testHasValueAfterRemoveAndPut()
    {
        $instance = $this->getInstance(['a' => 1]);
        $this->assertTrue($instance->hasValue(1));

        $instance->remove('a');
        $this->assertFalse($instance->hasValue(1));

        $instance->put('a', 1);
        $this->assertTrue($instance->hasValue(1));
    }
}
