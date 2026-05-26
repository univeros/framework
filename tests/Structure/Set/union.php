<?php
namespace Altair\Tests\Structure\Set;

use Altair\Tests\Structure\HashableObject;

trait union
{
    public static function unionDataProvider(): array
    {
        // Values in A and values in B.
        // A, B, expected result
        return [
            [[],          [],       []],
            [[],          ['a'],    ['a']],
            [['a'],       ['a'],    ['a']],
            [['a'],       ['b'],    ['a', 'b']],
            [['a', 'b'],  [],       ['a', 'b']],
        ];
    }

    /**
     * @dataProvider unionDataProvider
     */
    public function testUnion(array $initial, array $values, array $expected): void
    {
        $a = static::getInstance($initial);
        $b = static::getInstance($values);

        $this->assertEquals($expected, $a->union($b)->toArray());
    }

    /**
     * @dataProvider unionDataProvider
     */
    public function testUnionWithSelf(array $initial, array $values, array $expected): void
    {
        $a = static::getInstance($initial);
        $this->assertEquals($initial, $a->union($a)->toArray());
    }

    public function testUnionWhenOperatingOnSetsWithObjectsWithNonZeroHash(): void
    {
        $a = new HashableObject('a', random_int(0, mt_getrandmax()));
        $b = new HashableObject('b', random_int(0, mt_getrandmax()));

        $setA = static::getInstance([$a]);
        $setB = static::getInstance([$b]);

        $this->assertToArray([$a, $b], $setA->union($setB));
    }

    public function testUnionWhenOperatingOnSetsWithObjectsWithZeroHash(): void
    {
        $a = new HashableObject('a', 0);
        $b = new HashableObject('b', 0);

        $setA = static::getInstance([$a]);
        $setB = static::getInstance([$b]);

        $this->assertToArray([$a, $b], $setA->union($setB));
    }

    // /**
    //  * @dataProvider unionDataProvider
    //  */
    // public function testUnionOperator(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $b = static::getInstance($values);

    //     $this->assertEquals($expected, ($a | $b)->toArray());
    // }

    // /**
    //  * @dataProvider unionDataProvider
    //  */
    // public function testUnionOperatorAssign(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $b = static::getInstance($values);

    //     $a |= $b;
    //     $this->assertEquals($expected, $a->toArray());
    // }

    // /**
    //  * @dataProvider unionDataProvider
    //  */
    // public function testUnionOperatorWithSelf(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $this->assertEquals($initial, ($a | $a)->toArray());
    // }

    // /**
    //  * @dataProvider unionDataProvider
    //  */
    // public function testUnionOperatorAssignWithSelf(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);

    //     $a |= $a;
    //     $this->assertEquals($initial, $a->toArray());
    // }
}
