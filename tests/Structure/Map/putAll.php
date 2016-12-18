<?php
namespace Altair\Tests\Structure\Map;

trait putAll
{
    public function putAllDataProvider()
    {
        // values, values
        return [
            [[]],
            [['a']],
            [['a', 'b']],
            [['a', 'b', 'c']],
            [$this->sample()],
            [range(1, self::MANY)],
        ];
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAll(array $values)
    {
        $instance = $this->getInstance();
        $instance->putAll($values);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllUsingIterator(array $values)
    {
        $instance = $this->getInstance();
        $instance->putAll(new \ArrayIterator($values));
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromSet(array $values)
    {
        $instance = $this->getInstance();
        $set = new \Altair\Structure\Set($values);
        $instance->putAll($set);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromMap(array $values)
    {
        $instance = $this->getInstance();
        $map = new \Altair\Structure\Map($values);
        $instance->putAll($map);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromVector(array $values)
    {
        $instance = $this->getInstance();
        $vector = new \Altair\Structure\Vector($values);
        $instance->putAll($vector);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromDeque(array $values)
    {
        $instance = $this->getInstance();
        $deque = new \Altair\Structure\Deque($values);
        $instance->putAll($deque);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromStack(array $values)
    {
        $instance = $this->getInstance();
        $stack = new \Altair\Structure\Stack(array_reverse($values));
        $instance->putAll($stack);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $stack);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromQueue(array $values)
    {
        $instance = $this->getInstance();
        $queue = new \Altair\Structure\Queue($values);
        $instance->putAll($queue);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $queue);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromPriorityQueue(array $values)
    {
        $instance = $this->getInstance();
        $queue = new \Altair\Structure\PriorityQueue();

        foreach ($values as $value) {
            $queue->push($value, 0);
        }

        $instance->putAll($queue);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $queue);
    }
}
