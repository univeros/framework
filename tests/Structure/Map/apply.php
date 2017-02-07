<?php
namespace Altair\Tests\Structure\Map;

trait apply
{
    public function applyDataProvider()
    {
        // values, callback
        return [
            [
                [],
                function () {
                }
            ],

            [
                [1, 2, 3],
                function ($k, $v) {
                    return $v * 2;
                }
            ],
        ];
    }

    /**
     * @dataProvider applyDataProvider
     */
    public function testApply(array $values, callable $callback)
    {
        $instance = $this->getInstance($values);
        $instance->apply($callback);

        $expected = array_map($callback, array_keys($values), $values);
        $this->assertToArray($expected, $instance);
    }

    public function testApplyCallbackThrowsException()
    {
        $instance = $this->getInstance([1, 2, 3]);

        try {
            $instance->apply(
                function ($value) {
                    throw new \Exception();
                }
            );
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
            $instance->apply(
                function ($key, $value) {
                    if ($value === 3) {
                        throw new \Exception();
                    }

                    return '*';
                }
            );
        } catch (\Exception $e) {
            $this->assertToArray(['*', '*', 3], $instance);

            return;
        }

        $this->fail('Exception should have been caught');
    }

    /**
     * @expectedException \Exception
     */
    public function testApplyDoesNotLeakWhenCallbackFails()
    {
        $instance = $this->getInstance(
            [
                'a' => new \stdClass(),
                'b' => new \stdClass(),
                'c' => new \stdClass(),
            ]
        );


        $instance->apply(
            function ($key, $value) {
                if ($key === 'c') {
                    throw new \Exception();
                }
            }
        );

    }
}
