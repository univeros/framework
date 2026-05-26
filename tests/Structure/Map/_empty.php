<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait _empty
{
    public static function emptyDataProvider(): array
    {
        // initial, key, empty
        return [
            [['a' => 0], 'a', true],
            [['a' => 1], 'a', false],
            [['a' => false], 'a', true],
            [['a' => null], 'a', true],
            [[], 'a', true],
        ];
    }

    #[DataProvider('emptyDataProvider')]
    public function testArrayAccessEmpty(array $initial, mixed $key, bool $empty): void
    {
        $instance = static::getInstance();

        foreach ($initial as $key => $value) {
            $instance->put($key, $value);
        }

        $this->assertEquals($empty, empty($instance[$key]));
    }

    #[DataProvider('emptyDataProvider')]
    public function testArrayAccessEmptyByReference(array $initial, mixed $key, bool $empty): void
    {
        $instance = static::getInstance([$initial]);
        $this->assertEquals($empty, empty($instance[0][$key]));
    }
}
