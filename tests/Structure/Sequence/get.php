<?php
namespace Altair\Tests\Structure\Sequence;

trait get
{
    public function getDataProvider()
    {
        // initial, index, return
        return [
            [[0], 0, 0],

            [['a'], 0, 'a'],

            [['a', 'b'], 0, 'a'],
            [['a', 'b'], 1, 'b'],

            [['a', 'b', 'c'], 0, 'a'],
            [['a', 'b', 'c'], 1, 'b'],
            [['a', 'b', 'c'], 2, 'c'],
        ];
    }

    /**
     * @dataProvider getDataProvider
     * @param mixed $index
     * @param mixed $return
     */
    public function testGet(array $initial, $index, $return)
    {
        $instance = $this->getInstance($initial);

        $returned = $instance->get($index);

        $this->assertEquals(count($initial), count($instance));
        $this->assertEquals($return, $returned);
    }

    /**
     * @dataProvider outOfRangeDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testGetIndexOutOfRange($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance->get($index);
    }

    /**
     * @dataProvider badIndexDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testGetIndexBadIndex($initial, $index)
    {
        $instance = $this->getInstance();
        $this->expectWrongIndexTypeException();
        $instance->get($index);
    }

    /**
     * @dataProvider getDataProvider
     * @param mixed $index
     * @param mixed $return
     */
    public function testArrayAccessGet(array $initial, $index, $return)
    {
        $instance = $this->getInstance($initial);
        $this->assertEquals($return, $instance[$index]);
    }

    /**
     * @dataProvider badIndexDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessGetIndexBadIndex($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->expectWrongIndexTypeException();
        $instance[$index];
    }

    /**
     * @dataProvider outOfRangeDataProvider
     * @param mixed $initial
     * @param mixed $index
     */
    public function testArrayAccessGetIndexOutOfRange($initial, $index)
    {
        $instance = $this->getInstance($initial);
        $this->expectIndexOutOfRangeException();
        $instance[$index];
    }

    public function testArrayAccessGetByReference()
    {
        $instance = $this->getInstance([[1]]);
        $this->assertEquals(1, $instance[0][0]);
    }
}
