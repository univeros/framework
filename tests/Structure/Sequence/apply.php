<?php
namespace Altair\tests\Structure\Sequence;

trait apply
{
    public function applyDataProvider()
    {
        // values, callback
        return [

            //
            [[], function () {
            }],

            //
            [['A', 'B'], 'strtolower'],

            //
            [[new \stdClass()], 'spl_object_hash'],

            //
            [[1, 2, 3], function ($v) {
                return $v * 2;
            }],
        ];
    }

    /**
     * @dataProvider applyDataProvider
     */
    public function testApply(array $values, callable $callback)
    {
        $instance = $this->getInstance($values);

        $instance->apply($callback);
        $expected = array_map($callback, $values);

        $this->assertToArray($expected, $instance);
    }

    public function testApplyCallbackThrowsException()
    {
        $instance = $this->getInstance([1, 2, 3]);

        try {
            $instance->apply(function ($value) {
                throw new \Exception();
            });
        } catch (\Exception $e) {
            $this->assertToArray([1, 2, 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testApplyCallbackThrowsExceptionLaterOn()
    {
        $instance = $this->getInstance([1, 2, 3]);

        try {
            $instance->apply(function ($value) {
                if ($value === 3) {
                    throw new \Exception();
                } else {
                    return $value * 2;
                }
            });
        } catch (\Exception $e) {
            $this->assertToArray([2, 4, 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testApplyDoesNotCallByReference()
    {
        $instance = $this->getInstance([1, 2, 3]);

        $instance->apply(function ($value) {
            $before = $value;
            $value = null;

            return $before;
        });

        $this->assertToArray([1, 2, 3], $instance);
    }
}
