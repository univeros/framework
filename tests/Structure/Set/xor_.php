<?php
namespace Altair\Tests\Structure\Set;


use PHPUnit\Framework\Attributes\DataProvider;
trait xor_
{
    public static function xorDataProvider(): array
    {
        // Values in either A or B, but not both.
        // A, B, expected result
        return [
            [[],          [],           []],
            [['a'],       ['b'],        ['a', 'b']],
            [['a', 'b'],  ['b'],        ['a']],
            [['a', 'b'],  ['b', 'a'],   []],
        ];
    }

    #[DataProvider('xorDataProvider')]
    public function testXor(array $a, array $b, array $expected): void
    {
        $a = static::getInstance($a);
        $b = static::getInstance($b);

        $this->assertEquals($expected, $a->xor($b)->toArray());
    }

    #[DataProvider('xorDataProvider')]
    public function testXorWithSelf(array $a, array $b, array $expected): void
    {
        $a = static::getInstance($a);
        $this->assertEquals([], $a->xor($a)->toArray());
    }

    /**
     * @see https://github.com/php-ds/extension/issues/53
     */
    public function testXorAfterDiff(): void
    {
        $a = static::getInstance(['guest', 'member']);
        $b = static::getInstance(['member', 'nothing']);

        $k = $a->diff($b);  // [guest]
        $x = $a->xor($k);   // [guest, member] ^ [guest] = [member]

        $this->assertToArray(['member'], $x);
    }

    // /**
    //  * @dataProvider xorDataProvider
    //  */
    // public function testXorOperator(array $a, array $b, array $expected)
    // {
    //     $a = static::getInstance($a);
    //     $b = static::getInstance($b);

    //     $this->assertEquals($expected, ($a ^ $b)->toArray());
    // }

    // /**
    //  * @dataProvider xorDataProvider
    //  */
    // public function testXorOperatorAssign(array $a, array $b, array $expected)
    // {
    //     $a = static::getInstance($a);
    //     $b = static::getInstance($b);

    //     $a ^= $b;
    //     $this->assertEquals($expected, $a->toArray());
    // }

    // /**
    //  * @dataProvider xorDataProvider
    //  */
    // public function testXorOperatorWithSelf(array $a, array $b, array $expected)
    // {
    //     $a = static::getInstance($a);
    //     $this->assertEquals([], ($a ^ $a)->toArray());
    // }

    // /**
    //  * @dataProvider xorDataProvider
    //  */
    // public function testXorOperatorAssignWithSelf(array $a, array $b, array $expected)
    // {
    //     $a = static::getInstance($a);

    //     $a ^= $a;
    //     $this->assertEquals([], $a->toArray());
    // }
}
