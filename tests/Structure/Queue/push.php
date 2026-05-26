<?php
namespace Altair\Tests\Structure\Queue;

trait push
{
    public static function pushDataProvider()
    {
        return static::basicDataProvider();
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testPushVariadic(array $values, array $expected)
    {
        $instance = static::getInstance();

        $instance->push(...$values);

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testPush(array $values, array $expected)
    {
        $instance = static::getInstance();

        foreach ($values as $value) {
            $instance->push($value);
        }

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testArrayAccessPush(array $values, array $expected)
    {
        $instance = static::getInstance();

        foreach ($values as $value) {
            $instance[] = $value;
        }

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    public function testPushCircularReference()
    {
        $instance = static::getInstance();
        $instance->push($instance);
        $this->assertToArray([$instance], $instance);
    }
}
