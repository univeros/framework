<?php
namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Sequence\_clone;
use Altair\Tests\Structure\Sequence\_echo;
use Altair\Tests\Structure\Sequence\_empty;
use Altair\Tests\Structure\Sequence\_foreach;
use Altair\Tests\Structure\Sequence\_isset;
use Altair\Tests\Structure\Sequence\_jsonEncode;
use Altair\Tests\Structure\Sequence\_list;
use Altair\Tests\Structure\Sequence\_serialize;
use Altair\Tests\Structure\Sequence\_unset;
use Altair\Tests\Structure\Sequence\_var_dump;
use Altair\Tests\Structure\Deque\__construct;
use Altair\Tests\Structure\Deque\allocate;
use Altair\Tests\Structure\Deque\capacity;
use Altair\Tests\Structure\Deque\insert;
use Altair\Tests\Structure\Deque\remove;
use Altair\Tests\Structure\Deque\slice;
use Altair\Tests\Structure\Sequence\apply;
use Altair\Tests\Structure\Sequence\clear;
use Altair\Tests\Structure\Sequence\contains;
use Altair\Tests\Structure\Sequence\copy;
use Altair\Tests\Structure\Sequence\count;
use Altair\Tests\Structure\Sequence\filter;
use Altair\Tests\Structure\Sequence\find;
use Altair\Tests\Structure\Sequence\first;
use Altair\Tests\Structure\Sequence\get;
use Altair\Tests\Structure\Sequence\isEmpty;
use Altair\Tests\Structure\Sequence\join;
use Altair\Tests\Structure\Sequence\last;
use Altair\Tests\Structure\Sequence\map;
use Altair\Tests\Structure\Sequence\merge;
use Altair\Tests\Structure\Sequence\pop;
use Altair\Tests\Structure\Sequence\push;
use Altair\Tests\Structure\Sequence\reduce;
use Altair\Tests\Structure\Sequence\reverse;
use Altair\Tests\Structure\Sequence\rotate;
use Altair\Tests\Structure\Sequence\set;
use Altair\Tests\Structure\Sequence\shift;
use Altair\Tests\Structure\Sequence\sort;
use Altair\Tests\Structure\Sequence\sum;
use Altair\Tests\Structure\Sequence\toArray;
use Altair\Tests\Structure\Sequence\unshift;
use Altair\Structure\Deque;

class DequeTest extends AbstractCollectionTest
{
    use _clone;
    use _echo;
    use _empty;
    use _foreach;
    use _isset;
    use _jsonEncode;
    use _list;
    use _serialize;
    use _unset;
    use _var_dump;

    use __construct;
    use allocate;
    use capacity;
    use insert;
    use remove;
    use slice;

    use apply;
    use clear;
    use contains;
    use copy;
    use count;
    use filter;
    use find;
    use first;
    use get;
    use Sequence\insert;
    use isEmpty;
    use join;
    use last;
    use map;
    use merge;
    use pop;
    use push;
    use reduce;
    use Sequence\remove;
    use reverse;
    use rotate;
    use set;
    use shift;
    use Sequence\slice;
    use sort;
    use sum;
    use toArray;
    use unshift;

    public function testReallocatingWhenHeadNotAtZero(): void
    {
        $instance = $this->getInstance();

        $instance->push('a', 'b', 'c', 'd');
        $instance->shift();
        $instance->shift();

        foreach (range(0, self::MANY) as $value) {
            $instance->push($value);
        }

        $expected = array_merge(['c', 'd'], range(0, self::MANY));

        $this->assertToArray($expected, $instance);
    }

    public function testReallocatingWhenHeadHasWrapped(): void
    {
        $instance = $this->getInstance();

        $instance->push('a');
        $instance->push('b');
        $instance->push('c');       // [a, b, c, _, _, _, _, _]

        $instance->unshift('z');
        $instance->unshift('y');
        $instance->unshift('x');    // [a, b, c, _, _, x, y, z]
        //           T     H

        $instance->push('d');
        $instance->push('e');       // [a, b, c, d, e, x, y, z]
        //                 T
        //                 H

        $instance->push('f');

        $expected = ['x', 'y', 'z', 'a', 'b', 'c', 'd', 'e', 'f'];

        $this->assertToArray($expected, $instance);
    }

    public function testRealignmentOfWrappedBufferWithLargeTempSpace(): void
    {
        $instance = $this->getInstance();   // [_, _, _, _, _, _, _, _]

        $instance->push('c', 'd');
        $instance->unshift('a', 'b');       // [c, d, _, _, _, _, a, b]
        //        T           H
        //
        // Temporary space: 4
        // Wrapped values:  2

        // if the number of free slots >= number of wrapped values

        // When we sort the deque, the internal buffer will have to be realigned
        // to zero. In this particular case, there is enough temporary space
        // between the tail and the head to push the left partition forward,
        // and pull the right partition back to zero. This avoids an allocation,
        // which would be necessary in the case where the isn't enough space.

        $expected = ['d', 'c', 'b', 'a'];
        $this->assertToArray($expected, $instance->sort(function ($a, $b): int {
            return $b <=> $a; // Reverse
        }));

        ////////////////////////////////////////////////////////////////
        // Also test the boundary case, where the number of wrapped values
        // equals the amount of free space in the buffer.

        $instance = $this->getInstance();   // [_, _, _, _, _, _, _, _]

        $instance->push('c', 'd', 'e', 'f');
        $instance->unshift('a', 'b');       // [c, d, e, f, _, _, a, b]
        //              T     H
        //
        // Temporary space: 2
        // Wrapped values:  2

        $expected = ['f', 'e', 'd', 'c', 'b', 'a'];
        $this->assertToArray($expected, $instance->sort(function ($a, $b): int {
            return $b <=> $a; // Reverse
        }));

        ////////////////////////////////////////////////////////////////
        // Also test for assurance when there isn't enough space.

        $instance = $this->getInstance();   // [_, _, _, _, _, _, _, _]

        $instance->push('c', 'd', 'e', 'f', 'g');
        $instance->unshift('a', 'b');       // [c, d, e, f, g, _, a, b]
        //                 T  H
        //
        // Temporary space: 1
        // Wrapped values:  2

        $expected = ['g', 'f', 'e', 'd', 'c', 'b', 'a'];
        $this->assertToArray($expected, $instance->sort(function ($a, $b): int {
            return $b <=> $a; // Reverse
        }));
    }

    protected static function getInstance(array $values = []): Deque
    {
        return new Deque($values);
    }
}
