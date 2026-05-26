<?php
namespace Altair\Tests\Structure\Map;

trait reduce
{
    public static function reduceDataProvider(): array
    {
        // values, initial, callback, expected
        return [
            // Test reducing an empty map returns the initial
            [
                [],
                1,
                function (): void {
                },
                1,
            ],

            // Test reducing strings by concatenating them.
            [
                ['a', 'b', 'c'],
                '/',
                fn($c, $k, $v): string => implode('-', func_get_args()),
                '/-0-a-1-b-2-c',
            ],

            // Test reducing mixed numeric values by multiplying them.
            [
                [1, 2.5, '3'],
                2,
                fn($c, $k, $v): int|float => $c * $v,
                15,
            ],
        ];
    }

    public static function reduceWithoutInitialDataProvider(): array
    {
        // values, callback, expected
        return [
            // Test reducing an empty map returns the initial
            [
                [],
                function (): void {
                },
                null,
            ],

            // Test reducing strings by concatenating them.
            [
                ['a', 'b', 'c'],
                fn($c, $k, $v): string => implode('-', func_get_args()),
                '-0-a-1-b-2-c',
            ],

            // Test reducing mixed numeric values by multiplying them.
            [
                [1, 2.5, '3'],
                fn($c, $k, $v): int|float => $c * $v,
                0,
            ],
        ];
    }

    /**
     * @dataProvider reduceDataProvider
     */
    public function testReduce(array $values, mixed $initial, callable $callback, mixed $expected): void
    {
        $instance = static::getInstance($values);

        $reduced = $instance->reduce($callback, $initial);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    /**
     * @dataProvider reduceWithoutInitialDataProvider
     */
    public function testReduceWithoutInitial(array $values, callable $callback, mixed $expected): void
    {
        $instance = static::getInstance($values);

        $reduced = $instance->reduce($callback);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    public function testReduceCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $key, $value): void {
                throw new \Exception();
            });
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testReduceCallbackThrowsExceptionLaterOn(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $key, $value): void {
                if ($value === 3) {
                    throw new \Exception();
                }
            });
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }
}
