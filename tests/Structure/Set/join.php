<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait join
{
    public static function joinDataProvider(): array
    {
        // values, glue
        $data = [];

        $glues = ['', '~', 1, false];
        $lengths = [0, 1, 2, 3, self::SOME, self::MANY];

        foreach ($lengths as $len) {
            foreach ($glues as $glue) {
                $data[] = [range(0, $len - 1), $glue];
            }
        }

        return $data;
    }

    #[DataProvider('joinDataProvider')]
    public function testJoin(array $values, mixed $glue): void
    {
        $instance = static::getInstance($values);
        $expected = implode($glue, $values);
        $this->assertEquals($expected, $instance->join($glue));
    }

    #[DataProvider('joinDataProvider')]
    public function testJoinWithoutGlue(array $values, mixed $glue): void
    {
        $instance = static::getInstance($values);
        $expected = implode('', $values);
        $this->assertEquals($expected, $instance->join());
    }
}
