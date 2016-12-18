<?php
namespace Altair\Tests\Structure\Set;

trait diff
{
    public function diffDataProvider()
    {
        // Values in A but not in B.
        // A, B, expected result
        return [
            [[],          [],           []],
            [['a'],       [],           ['a']],
            [['a'],       ['b'],        ['a']],
            [['a', 'b'],  ['a'],        ['b']],
            [['a', 'b'],  ['b'],        ['a']],
            [['a', 'b'],  ['a', 'b'],   []],
        ];
    }

    /**
     * @dataProvider diffDataProvider
     */
    public function testDiff(array $a, array $b, array $expected)
    {
        $a = $this->getInstance($a);
        $b = $this->getInstance($b);

        $this->assertEquals($expected, $a->diff($b)->toArray());
    }

    // /**
    //  * @dataProvider diffDataProvider
    //  */
    // public function testDiffOperator(array $a, array $b, array $expected)
    // {
    //     $a = $this->getInstance($a);
    //     $b = $this->getInstance($b);

    //     $this->assertEquals($expected, ($a - $b)->toArray());
    // }

    // /**
    //  * @dataProvider diffDataProvider
    //  */
    // public function testDiffOperatorAssign(array $a, array $b, array $expected)
    // {
    //     $a = $this->getInstance($a);
    //     $b = $this->getInstance($b);

    //     $a -= $b;
    //     $this->assertEquals($expected, $a->toArray());
    // }

    /**
     * @dataProvider diffDataProvider
     */
    public function testDiffWithSelf(array $a, array $b, array $expected)
    {
        $a = $this->getInstance($a);
        $this->assertEquals([], $a->diff($a)->toArray());
    }

    // /**
    //  * @dataProvider diffDataProvider
    //  */
    // public function testDiffOperatorWithSelf(array $a, array $b, array $expected)
    // {
    //     $a = $this->getInstance($a);
    //     $this->assertEquals([], ($a - $a)->toArray());
    // }

    // /**
    //  * @dataProvider diffDataProvider
    //  */
    // public function testDiffOperatorAssignWithSelf(array $a, array $b, array $expected)
    // {
    //     $a = $this->getInstance($a);

    //     $a -= $a;
    //     $this->assertEquals([], $a->toArray());
    // }
}
