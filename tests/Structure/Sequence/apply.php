<?php
namespace Altair\Tests\Structure\Sequence;

trait apply
{
    public static function applyDataProvider(): array
    {
        // values, callback
        return [
            //
            [[], function (): void {
            }],

            //
            [['A', 'B'], 'strtolower'],

            //
            [[new \stdClass()], 'spl_object_hash'],

            //
            [[1, 2, 3], fn($v): int|float => $v * 2],
        ];
    }

    /**
     * @dataProvider applyDataProvider
     */
    public function testApply(array $values, callable $callback): void
    {
        $instance = static::getInstance($values);

        $instance->apply($callback);

        $expected = array_map($callback, $values);

        $this->assertToArray($expected, $instance);
    }

    public function testApplyCallbackThrowsException(): void
    {
        $instance = static::getInstance([1, 2, 3]);

        try {
            $instance->apply(function ($value): void {
                throw new \Exception();
            });
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
            $instance->apply(function ($value): int|float {
                if ($value === 3) {
                    throw new \Exception();
                }

                return $value * 2;
            });
        } catch (\Exception) {
            $this->assertToArray([2, 4, 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testApplyDoesNotCallByReference(): void
    {
        $instance = static::getInstance([1, 2, 3]);

        $instance->apply(function ($value) {
            $before = $value;
            $value = null;

            return $before;
        });

        $this->assertToArray([1, 2, 3], $instance);
    }
}
