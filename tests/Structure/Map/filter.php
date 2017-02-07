<?php
namespace Altair\Tests\Structure\Map;

trait filter
{
    public function filterDataProvider()
    {
        // values, callback, expected
        return [
            // Test filtering an empty sequence produces an empty sequence.
            [[], function () {
            }, []],

            // Test only including odd values.
            [[1, 2, 3], function ($k, $v) {
                return $k & 1;
            }, [1 => 2]],
            [[1, 2, 3], function ($k, $v) {
                return $v & 1;
            }, [0 => 1, 2 => 3]],

            // Test not asking for the value.
            [[1, 2, 3], function ($k) {
                return $k & 1;
            }, [1 => 2]],
        ];
    }

    /**
     * @dataProvider filterDataProvider
     */
    public function testFilter(array $values, callable $callback, array $expected)
    {
        $instance = $this->getInstance($values);

        $filtered = $instance->filter($callback);

        $this->assertToArray($values, $instance);
        $this->assertEquals($expected, $filtered->toArray());
    }

    public function testFilterCallbackThrowsException()
    {
        $instance = $this->getInstance([1, 2, 3]);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($key, $value) {
                throw new \Exception();
            });
        } catch (\Exception $e) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    public function testFilterCallbackThrowsExceptionLaterOn()
    {
        $instance = $this->getInstance([1, 2, 3]);
        $filtered = null;

        try {
            $filtered = $instance->filter(function ($key, $value) {
                if ($value === 3) {
                    throw new \Exception();
                }
            });
        } catch (\Exception $e) {
            $this->assertToArray([1, 2, 3], $instance);
            $this->assertNull($filtered);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    /**
     * @expectedException \Exception
     */
    public function testFilterDoesNotLeakWhenCallbackFails()
    {
        $instance = $this->getInstance([
            'a' => new \stdClass(),
            'b' => new \stdClass(),
            'c' => new \stdClass(),
        ]);


        $instance->filter(function ($key, $value) {
            if ($key === 'c') {
                throw new \Exception();
            }
        });
    }

    public function testFilterWithoutCallable()
    {
        $values = [
            'a' => 1,
            'b' => 2,
            'c' => true,
            'd' => false,
            'e' => [],
            'f' => 0,
        ];

        $instance = $this->getInstance($values);
        $this->assertToArray(array_filter($values), $instance->filter());
    }
}
