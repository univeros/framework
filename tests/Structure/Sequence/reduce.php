<?php
namespace Altair\Tests\Structure\Sequence;

trait reduce
{
    public static function reduceDataProvider(): array
    {
        // values, initial, callback
        return [
            // Test reducing an empty sequence returns the initial
            [[], 1, function (): void {
            }],

            // Test reducing strings by concatenating them.
            [['a', 'b', 'c'], '', fn($c, $v): string => $c . $v],

            // Test reducing mixed numeric values by multiplying them.
            [[1, 2.5, '3'], 2, fn($c, $v): int|float => $c * $v],
        ];
    }

    /**
     * @dataProvider reduceDataProvider
     */
    public function testReduce(array $values, mixed $initial, callable $callback): void
    {
        $instance = static::getInstance($values);

        $reduced = $instance->reduce($callback, $initial);
        $expected = array_reduce($values, $callback, $initial);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    /**
     * @dataProvider reduceDataProvider
     */
    public function testReduceWithoutInitial(array $values, mixed $initial, callable $callback): void
    {
        $instance = static::getInstance($values);

        $reduced = $instance->reduce($callback);
        $expected = array_reduce($values, $callback);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    public function testReduceCallbackThrowsException(): void
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $value): void {
                throw new \Exception();
            });
        } catch (\Exception) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testReduceCallbackThrowsExceptionLaterOn(): void
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return $value;
            });
        } catch (\Exception) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testReduceCallbackDoesNotLeakOnFailure(): void
    {
        $instance = static::getInstance(['a', 'b', 'c']);
        $reduced = null;
        try {
            $reduced = $instance->reduce(function ($carry, $value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return $value;
            });
        } catch (\Exception) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($reduced);
            return;
        }

        $this->fail('Exception should have been caught');
    }
}
