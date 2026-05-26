<?php
namespace Altair\Tests\Structure\Map;

trait _isset
{
    public static function issetDataProvider(): array
    {
        // initial, key, isset
        return [
            [['a' => 0], 'a', true],
            [['a' => 1], 'a', true],
            [['a' => false], 'a', true],
            [['a' => null], 'a', false],
            [[], 'a', false],
        ];
    }

    /**
     * @dataProvider issetDataProvider
     */
    public function testArrayAccessIsset(array $initial, mixed $key, bool $isset): void
    {
        $instance = static::getInstance();

        foreach ($initial as $key => $value) {
            $instance->put($key, $value);
        }

        $this->assertEquals($isset, isset($instance[$key]));
    }

    /**
     * @dataProvider issetDataProvider
     */
    public function testArrayAccessIssetByReference(array $initial, mixed $key, bool $isset): void
    {
        $instance = static::getInstance([$initial]);
        $this->assertEquals($isset, isset($instance[0][$key]));
    }
}
