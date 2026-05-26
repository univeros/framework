<?php
namespace Altair\Tests\Structure\Queue;


use PHPUnit\Framework\Attributes\DataProvider;
trait _jsonEncode
{
    #[DataProvider('basicDataProvider')]
    public function testJsonEncode(array $initial, array $expected): void
    {
        $instance = static::getInstance($initial);
        $this->assertEquals(json_encode($expected), json_encode($instance));
    }
}
