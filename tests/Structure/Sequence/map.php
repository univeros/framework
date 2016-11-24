<?php
namespace Altair\tests\Structure\Sequence;

trait map
{
    public function mapDataProvider()
    {
        // values, callback
        return [

            // Test mapping an empty sequence produces an empty sequence.
            [[], function () {
            }],

            // Test mapping string values using a string callable.
            [['A', 'B'], 'strtolower'],

            // Test mapping a sequence containing an object (for refcount?)
            [[new \stdClass()], 'spl_object_hash'],

            // Test basic mapping where integers are doubled.
            [[1, 2, 3], function ($v) {
                return $v * 2;
            }],
        ];
    }

    /**
     * @dataProvider mapDataProvider
     */
    public function testMap(array $values, callable $callback)
    {
        $instance = $this->getInstance($values);

        $mapped = $instance->map($callback);
        $expected = array_map($callback, $values);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $mapped->toArray());
    }

    public function testMapCallbackThrowsException()
    {
        $instance = $this->getInstance([1, 2, 3]);
        $mapped = null;

        try {
            $mapped = $instance->map(function ($value) {
                throw new \Exception();
            });
        } catch (\Exception $e) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($mapped);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testMapCallbackThrowsExceptionLaterOn()
    {
        $instance = $this->getInstance([1, 2, 3]);
        $mapped = null;

        try {
            $mapped = $instance->map(function ($value) {
                if ($value === 3) {
                    throw new \Exception();
                }

                return $value;
            });
        } catch (\Exception $e) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($mapped);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testMapDoesNotLeakWhenCallbackFails()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);

        try {
            $instance->map(function ($value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return $value;
            });
        } catch (\Exception $e) {
            return;
        }

        $this->fail('Exception should have been caught');
    }
}
