<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait intersect
{
    public static function intersectDataProvider(): array
    {
        // Values in A that are also in B.
        // A, B, expected result
        return [
            [[],                [],         []],
            [['a'],             ['b'],      []],
            [['a'],             ['a'],      ['a']],
            [['a', 'b', 'c'],   ['a', 'b'], ['a', 'b']],
            [['a', 'b', 'c'],   ['b', 'c'], ['b', 'c']],
            [['a', 'b', 'c'],   ['c', 'd'], ['c']],
        ];
    }

    #[DataProvider('intersectDataProvider')]
    public function testIntersect(array $initial, array $values, array $expected): void
    {
        $a = static::getInstance($initial);
        $b = static::getInstance($values);

        $this->assertEquals($expected, $a->intersect($b)->toArray());
    }

    #[DataProvider('intersectDataProvider')]
    public function testIntersectWithSelf(array $initial, array $values, array $expected): void
    {
        $a = static::getInstance($initial);
        $this->assertEquals($initial, $a->intersect($a)->toArray());
    }

    /**
     * Test that contains still works after intersect.
     */
    public function testIntersectContains(): void
    {
        $ab = static::getInstance(['a', 'b']);
        $bc = static::getInstance(['b', 'c']);

        $b = $ab->intersect($bc);

        $this->assertToArray(['b'], $b);

        $this->assertFalse($b->contains('a'));
        $this->assertFalse($b->contains('c'));
        $this->assertTrue($b->contains('b'));
    }

    /**
     * Test that you can't add duplicates after an intersection.
     */
    public function testIntersectAdd(): void
    {
        $ab = static::getInstance(['a', 'b']);
        $bc = static::getInstance(['b', 'c']);

        $b = $ab->intersect($bc);
        $b->add('b');

        $this->assertToArray(['b'], $b);
    }

    // /**
    //  * @dataProvider intersectDataProvider
    //  */
    // public function testIntersectOperator(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $b = static::getInstance($values);

    //     $this->assertEquals($expected, ($a & $b)->toArray());
    // }

    // /**
    //  * @dataProvider intersectDataProvider
    //  */
    // public function testIntersectOperatorAssign(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $b = static::getInstance($values);

    //     $a &= $b;
    //     $this->assertEquals($expected, $a->toArray());
    // }

    // /**
    //  * @dataProvider intersectDataProvider
    //  */
    // public function testIntersectOperatorWithSelf(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);
    //     $this->assertEquals($initial, ($a & $a)->toArray());
    // }

    // /**
    //  * @dataProvider intersectDataProvider
    //  */
    // public function testIntersectOperatorAssignWithSelf(array $initial, array $values, array $expected)
    // {
    //     $a = static::getInstance($initial);

    //     $a &= $a;
    //     $this->assertEquals($initial, $a->toArray());
    // }
}
