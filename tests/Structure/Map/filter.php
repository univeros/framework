<?php
namespace Altair\Tests\Structure\Map;

trait filter
{
    public static function filterDataProvider(): array
    {
        // values, callback, expected
        return [
            // Test filtering an empty sequence produces an empty sequence.
            [[], function (): void {
            }, []],

            // Test only including odd values.
            [[1, 2, 3], fn($k, $v): int => $k & 1, [1 => 2]],
            [[1, 2, 3], fn($k, $v): int => $v & 1, [0 => 1, 2 => 3]],

            // Test not asking for the value.
            [[1, 2, 3], fn($k): int => $k & 1, [1 => 2]],
        ];
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilter(array $values, callable $callback, array $expected): void
    {
        $instance = static::getInstance($values);

        $filtered = $instance->filter($callback);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    public function testFilterCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($key, $value): void {
                throw new \Exception();
            });
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterCallbackThrowsExceptionLaterOn(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($key, $value): void {
                if ($value === 3) {
                    throw new \Exception();
                }
            });
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterDoesNotLeakWhenCallbackFails(): void
    {
        $instance = static::getInstance([
            "a" => new \stdClass(),
            "b" => new \stdClass(),
            "c" => new \stdClass(),
        ]);
        $filtered = null;
        try {
            $filtered = $instance->filter(function ($key, $value): void {
                if ($key === "c") {
                    throw new \Exception();
                }
            });
        } catch (\Exception) {
            $this->assertNull($filtered);
            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterWithoutCallable(): void
    {
        $values = [
            'a' => 1,
            'b' => 2,
            'c' => true,
            'd' => false,
            'e' => [],
            'f' => 0,
        ];

        $instance = static::getInstance($values);
        $this->assertToArray(array_filter($values), $instance->filter());
    }
}
