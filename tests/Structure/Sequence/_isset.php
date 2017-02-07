<?php
namespace Altair\Tests\Structure\Sequence;

trait _isset
{
    public function issetDataProvider()
    {
        // initial, index, isset
        return [
            [[0], 0, true],

            [[null], 0, false],

            [['a'], 0, true],
            [['a'], 1, false],

            [['a', 'b'], 0, true],
            [['a', 'b'], 1, true],
            [['a', 'b'], 2, false],

            [[null, 'b'], 0, false],
            [['a', null], 1, false],

            [['a', 'b', 'c'], 0, true],
            [['a', 'b', 'c'], 1, true],
            [['a', 'b', 'c'], 2, true],
            [['a', 'b', 'c'], 3, false],

            [[null, 'b', 'c'], 0, false],
            [['a', null, 'c'], 1, false],
            [['a', 'b', null], 2, false],
        ];
    }

    /**
     * @dataProvider issetDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessIsset($initial, $index, bool $isset)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($isset, isset($instance[$index]));
    }

    /**
     * @dataProvider badIndexDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessIssetIndexBadIndex($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
    }

    /**
     * @dataProvider outOfRangeDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessIssetIndexOutOfRange($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->assertFalse(isset($instance[$index]));
    }

    /**
     * @dataProvider issetDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessIssetByReference($initial, $index, bool $isset)
    {
        $instance = $this->getInstance([$initial]);
        $this->assertEquals($isset, isset($instance[0][$index]));
    }
}
