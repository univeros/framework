<?php
namespace Altair\Tests\Structure\Sequence;


use PHPUnit\Framework\Attributes\DataProvider;
trait join
{
    public static function joinDataProvider(): array
    {
        // values, glue
        $data = [];

        $glues = ['', '~', 0, 1, false];
        $lengths = [0, 1, 2, 3, 10];
        $obj = static::getInstance();

        foreach ($lengths as $length) {
            foreach ($glues as $glue) {
                $data[] = [range(1, $length),            $glue]; // integers
                $data[] = [array_fill(0, $length, 'x'),  $glue]; // string
                $data[] = [array_fill(0, $length, $obj), $glue]; // objects
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
