<?php
namespace Altair\Tests\Structure\Set;

trait filter
{
    public static function filterDataProvider(): array
    {
        // values, callback
        return [
            [[], function (): void {
            }],

            // Test filtering with a string callable.
            [[0, 1, false, true], 'boolval'],

            // Test only including odd values.
            [[1, 2, 3], fn($v): int => $v & 1],
        ];
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilter(array $values, callable $callback): void
    {
        $instance = static::getInstance($values);

        $filtered = $instance->filter($callback);
        $expected = array_values(array_filter($values, $callback));

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilterWithoutCallback(array $values, callable $callback): void
    {
        $instance = static::getInstance($values);

        $filtered = $instance->filter();
        $expected = array_values(array_filter($values));

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    public function testFilterCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($value): void {
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
            $filtered = $instance->filter(function ($value): void {
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
}
