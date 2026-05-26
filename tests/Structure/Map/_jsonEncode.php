<?php
namespace Altair\Tests\Structure\Map;


use PHPUnit\Framework\Attributes\DataProvider;
trait _jsonEncode
{
    public static function jsonEncodeDataProvider()
    {
        return static::basicDataProvider();
    }

    #[DataProvider('jsonEncodeDataProvider')]
    public function testJsonEncode(array $initial, array $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals(json_encode($expected), json_encode($instance));
    }
}
