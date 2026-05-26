<?php
namespace Altair\Tests\Structure\Map;

trait map
{
    public static function mapDataProvider(): array
    {
        // values, callback
        return [
            // Test mapping an empty map produces an empty map.
            [
                [],
                function (): void {
                }
            ],

            // Test basic mapping where integers are doubled.
            [
                [1, 2, 3],
                fn($k, $v): int|float => $v * 2
            ],
        ];
    }

    /**
     * @dataProvider mapDataProvider
     */
    public function testMap(array $values, callable $callback): void
    {
        $instance = static::getInstance($values);

        $mapped = $instance->map($callback);
        $expected = array_map($callback, array_keys($values), $values);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $mapped->toArray());
    }

    public function testMapCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $mapped = null;

        try {
            $mapped = $instance->map(
                function ($value): void {
                    throw new \Exception();
                }
            );
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($mapped);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testMapCallbackThrowsExceptionLaterOn(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $mapped = null;

        try {
            $mapped = $instance->map(
                function ($key, $value): void {
                    if ($value === 3) {
                        throw new \Exception();
                    }
                }
            );
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($mapped);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testMapDoesNotLeakWhenCallbackFails(): void
    {
        $instance = static::getInstance([
            "a" => new \stdClass(),
            "b" => new \stdClass(),
            "c" => new \stdClass(),
        ]);
        $mapped = null;
        try {
            $mapped = $instance->map(function ($key, $value): void {
                if ($key === "c") {
                    throw new \Exception();
                }
            });
        } catch (\Exception) {
            $this->assertNull($mapped);
            return;
        }

        $this->fail('Exception should have been caught');
    }
}
