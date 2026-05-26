<?php
namespace Altair\Tests\Structure\Map;

use Altair\Structure\Set;
use Altair\Structure\Map;
use Altair\Structure\Vector;
use Altair\Structure\Deque;
use Altair\Structure\Stack;
use Altair\Structure\Queue;
use Altair\Structure\PriorityQueue;

trait putAll
{
    public static function putAllDataProvider(): array
    {
        // values, values
        return [
            [[]],
            [['a']],
            [['a', 'b']],
            [['a', 'b', 'c']],
            [static::sample()],
            [range(1, self::MANY)],
        ];
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAll(array $values): void
    {
        $instance = static::getInstance();
        $instance->putAll($values);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllUsingIterator(array $values): void
    {
        $instance = static::getInstance();
        $instance->putAll(new \ArrayIterator($values));
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromSet(array $values): void
    {
        $instance = static::getInstance();
        $set = new Set($values);
        $instance->putAll($set);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromMap(array $values): void
    {
        $instance = static::getInstance();
        $map = new Map($values);
        $instance->putAll($map);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromVector(array $values): void
    {
        $instance = static::getInstance();
        $vector = new Vector($values);
        $instance->putAll($vector);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromDeque(array $values): void
    {
        $instance = static::getInstance();
        $deque = new Deque($values);
        $instance->putAll($deque);
        $this->assertToArray($values, $instance);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromStack(array $values): void
    {
        $instance = static::getInstance();
        $stack = new Stack(array_reverse($values));
        $instance->putAll($stack);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $stack);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromQueue(array $values): void
    {
        $instance = static::getInstance();
        $queue = new Queue($values);
        $instance->putAll($queue);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $queue);
    }

    /**
     * @dataProvider putAllDataProvider
     */
    public function testPutAllFromPriorityQueue(array $values): void
    {
        $instance = static::getInstance();
        $queue = new PriorityQueue();

        foreach ($values as $value) {
            $queue->push($value, 0);
        }

        $instance->putAll($queue);
        $this->assertToArray($values, $instance);
        $this->assertCount(0, $queue);
    }
}
