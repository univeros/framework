<?php
namespace Altair\Tests\Structure\PriorityQueue;

trait push
{
    public static function pushDataProvider(): array
    {
        // initial, values, expected
        return [
            [[], ['a' => 1, 'b' => 2], ['b', 'a']],
            [[], ['a' => 1, 'b' => 1], ['a', 'b']],

            [['a' => 1], ['b' => 2], ['b', 'a']],
            [['a' => 1], ['b' => 1], ['a', 'b']],
        ];
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testPush(array $initial, array $values, array $expected): void
    {
        $instance = static::getInstance($initial);

        foreach ($values as $value => $priority) {
            $instance->push($value, $priority);
        }

        $this->assertToArray($expected, $instance);
    }

    public function testPushIdenticalValues(): void
    {
        $instance = static::getInstance();

        $instance->push('a', 1);
        $instance->push('a', 1);
        $instance->push('a', 1);

        $this->assertToArray(['a', 'a', 'a'], $instance);
    }

    public function testPushManyRandom(): void
    {
        $instance = static::getInstance();

        $reference = range(1, self::SOME);
        shuffle($reference);

        foreach ($reference as $index => $priority) {
            $instance->push($index, $priority);
        }

        asort($reference);
        $this->assertEmpty(array_diff_key($reference, $instance->toArray()));
    }

    public function testInsertionOrder(): void
    {
        $instance = static::getInstance();

        foreach (range(1, self::MANY) as $i) {
            $instance->push($i, 0);
        }

        foreach (range(1, self::MANY) as $i) {
            $this->assertEquals($i, $instance->pop());
        }
    }

    public function testPushCircularReference(): void
    {
        $instance = static::getInstance();
        $instance->push($instance, 1);
        $this->assertToArray([$instance], $instance);
    }
}
