<?php
namespace Altair\Tests\Structure\Sequence;

trait _unset
{
    /**
     * @dataProvider removeDataProvider
     * @param mixed $initial
     * @param mixed $index
     * @param mixed $return
     */
    public function testArrayAccessUnset($initial, $index, $return, array $expected)
    {
        $instance = $this->getInstance($initial);
        unset($instance[$index]);
        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    /**
     * @dataProvider badIndexDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessUnsetIndexBadIndex($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
        unset($instance[$index]);
    }

    /**
     * @dataProvider outOfRangeDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessUnsetIndexOutOfRange($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
        unset($instance[$index]);
    }

    public function testArrayAccessUnsetByReference()
    {
        $instance = $this->getInstance([[1]]);
        unset($instance[0][0]);

        $this->assertToArray([[]], $instance);
    }
}
