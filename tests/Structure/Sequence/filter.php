<?php
namespace Altair\Tests\Structure\Sequence;

trait filter
{
    public function filterDataProvider()
    {
        // values, callback
        return [
            // Test filtering an empty sequence produces an empty sequence.
            [[], function () {
            }],

            // Test filtering with a string callable.
            [[0, 1, false, true], 'boolval'],

            // Test only including odd values.
            [[1, 2, 3], function ($v) {
                return $v & 1;
            }],
        ];
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilter(array $values, callable $callback)
    {
        $instance = $this->getInstance($values);

        $filtered = $instance->filter($callback);
        $expected = array_values(array_filter($values, $callback));

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilterWithoutCallback(array $values, callable $callback)
    {
        $instance = $this->getInstance($values);

        $filtered = $instance->filter();
        $expected = array_values(array_filter($values));

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    public function testFilterCallbackThrowsException()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($value) {
                throw new \Exception();
            });
        } catch (\Exception $e) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterCallbackThrowsExceptionLaterOn()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return true;
            });
        } catch (\Exception $e) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterDoesNotLeakWhenCallbackFails()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($value) {
                if ($value === 'c') {
                    throw new \Exception();
                }

                return true;
            });
        } catch (\Exception $e) {
            $this->assertToArray(['a', 'b', 'c'], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }
}
