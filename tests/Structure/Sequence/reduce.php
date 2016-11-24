<?php
namespace Altair\tests\Structure\Sequence;

trait reduce
{
    public function reduceDataProvider()
    {
        // values, initial, callback
        return [

            // Test reducing an empty sequence returns the initial
            [[], 1, function () {
            }],

            // Test reducing strings by concatenating them.
            [['a', 'b', 'c'], '', function ($c, $v) {
                return $c . $v;
            }],

            // Test reducing mixed numeric values by multiplying them.
            [[1, 2.5, '3'], 2, function ($c, $v) {
                return $c * $v;
            }],
        ];
    }

    /**
     * @dataProvider reduceDataProvider
     */
    public function testReduce(array $values, $initial, callable $callback)
    {
        $instance = $this->getInstance($values);

        $reduced = $instance->reduce($callback, $initial);
        $expected = array_reduce($values, $callback, $initial);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    /**
     * @dataProvider reduceDataProvider
     */
    public function testReduceWithoutInitial(array $values, $initial, callable $callback)
    {
        $instance = $this->getInstance($values);

        $reduced = $instance->reduce($callback);
        $expected = array_reduce($values, $callback);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $reduced);
    }

    public function testReduceCallbackThrowsException()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $value) {
                throw new \Exception();
            });
        } catch (\Exception $e) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testReduceCallbackThrowsExceptionLaterOn()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $result = null;

        try {
            $result = $instance->reduce(function ($carry, $value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return $value;
            });
        } catch (\Exception $e) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($result);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testReduceCallbackDoesNotLeakOnFailure()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);

        try {
            $instance->reduce(function ($carry, $value) {
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
