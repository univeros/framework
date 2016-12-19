<?php
namespace Altair\Tests\Structure\Sequence;

trait join
{
    public function joinDataProvider()
    {
        // values, glue
        $data = [];

        $glues = ['', '~', 0, 1, false];
        $lengths = [0, 1, 2, 3, 10];
        $obj = $this->getInstance();

        foreach ($lengths as $length) {
            foreach ($glues as $glue) {
                $data[] = [range(1, $length),            $glue]; // integers
                $data[] = [array_fill(0, $length, 'x'),  $glue]; // string
                $data[] = [array_fill(0, $length, $obj), $glue]; // objects
            }
        }

        return $data;
    }

    /**
     * @dataProvider joinDataProvider
     * @param mixed $glue
     */
    public function testJoin(array $values, $glue)
    {
        $instance = $this->getInstance($values);
        $expected = implode($glue, $values);
        $this->assertEquals($expected, $instance->join($glue));
    }

    /**
     * @dataProvider joinDataProvider
     * @param mixed $glue
     */
    public function testJoinWithoutGlue(array $values, $glue)
    {
        $instance = $this->getInstance($values);
        $expected = implode($values);
        $this->assertEquals($expected, $instance->join());
    }
}
