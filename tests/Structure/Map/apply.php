<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait apply
{
    public static function applyDataProvider(): array
    {
        // values, callback
        return [
            [
                [],
                function (): void {
                }
            ],

            [
                [1, 2, 3],
                fn($k, $v): int|float => $v * 2
            ],
        ];
    }

    #[DataProvider('applyDataProvider')]
    public function testApply(array $values, callable $callback): void
    {
        $instance = static::getInstance($values);
        $instance->apply($callback);

        $expected = array_map($callback, array_keys($values), $values);
        $this->assertToArray($expected, $instance);
    }

    public function testApplyCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);

        try {
            $instance->apply(
                function ($value): void {
                    throw new \Exception();
                }
            );
        } catch (\Exception) {
            $this->assertToArray([1, 2, 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testApplyCallbackThrowsExceptionLaterOn(): void
    {
        $instance = static::getInstance([1, 2, 3]);

        try {
            $instance->apply(
                function ($key, $value): string {
                    if ($value === 3) {
                        throw new \Exception();
                    }

                    return '*';
                }
            );
        } catch (\Exception) {
            $this->assertToArray(['*', '*', 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testApplyDoesNotLeakWhenCallbackFails(): void
    {
        $instance = static::getInstance([
            "a" => new \stdClass(),
            "b" => new \stdClass(),
            "c" => new \stdClass(),
        ]);
        $result = null;

        try {
            $result = $instance->apply(function ($key, $value): void {
                if ($key === "c") {
                    throw new \Exception();
                }
            });
        } catch (\Exception) {
            $this->assertNull($result);
            return;
        }
    }
}
