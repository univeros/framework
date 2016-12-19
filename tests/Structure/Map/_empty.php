<?php
namespace Altair\Tests\Structure\Map;

trait _empty
{
    public function emptyDataProvider()
    {
        // initial, key, empty
        return [
            [['a' => 0], 'a', true],
            [['a' => 1], 'a', false],
            [['a' => false], 'a', true],
            [['a' => null], 'a', true],
            [[], 'a', true],
        ];
    }

    /**
     * @dataProvider emptyDataProvider
     * @param mixed $key
     */
    public function testArrayAccessEmpty(array $initial, $key, bool $empty)
    {
        $instance = $this->getInstance();

        foreach ($initial as $key => $value) {
            $instance->put($key, $value);
        }

        $this->assertEquals($empty, empty($instance[$key]));
    }

    /**
     * @dataProvider emptyDataProvider
     * @param mixed $key
     */
    public function testArrayAccessEmptyByReference(array $initial, $key, bool $empty)
    {
        $instance = $this->getInstance([$initial]);
        $this->assertEquals($empty, empty($instance[0][$key]));
    }
}
