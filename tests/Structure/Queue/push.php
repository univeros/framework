<?php
namespace Altair\Tests\Structure\Queue;


use PHPUnit\Framework\Attributes\DataProvider;
trait push
{
    public static function pushDataProvider()
    {
        return static::basicDataProvider();
    }

    #[DataProvider('pushDataProvider')]
    public function testPushVariadic(array $values, array $expected): void
    {
        $instance = static::getInstance();

        $instance->push(...$values);

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    #[DataProvider('pushDataProvider')]
    public function testPush(array $values, array $expected): void
    {
        $instance = static::getInstance();

        foreach ($values as $value) {
            $instance->push($value);
        }

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    #[DataProvider('pushDataProvider')]
    public function testArrayAccessPush(array $values, array $expected): void
    {
        $instance = static::getInstance();

        foreach ($values as $value) {
            $instance[] = $value;
        }

        $this->assertToArray($expected, $instance);
        $this->assertCount(count($expected), $instance);
    }

    public function testPushCircularReference(): void
    {
        $instance = static::getInstance();
        $instance->push($instance);
        $this->assertToArray([$instance], $instance);
    }
}
