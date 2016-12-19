<?php
namespace Altair\Tests\Structure;

class DequeTest extends AbstractCollectionTest
{
    use Sequence\_clone;
    use Sequence\_echo;
    use Sequence\_empty;
    use Sequence\_foreach;
    use Sequence\_isset;
    use Sequence\_jsonEncode;
    use Sequence\_list;
    use Sequence\_serialize;
    use Sequence\_unset;
    use Sequence\_var_dump;

    use Deque\__construct;
    use Deque\allocate;
    use Deque\capacity;
    use Deque\insert;
    use Deque\remove;
    use Deque\slice;

    use Sequence\apply;
    use Sequence\clear;
    use Sequence\contains;
    use Sequence\copy;
    use Sequence\count;
    use Sequence\filter;
    use Sequence\find;
    use Sequence\first;
    use Sequence\get;
    use Sequence\insert;
    use Sequence\isEmpty;
    use Sequence\join;
    use Sequence\last;
    use Sequence\map;
    use Sequence\merge;
    use Sequence\pop;
    use Sequence\push;
    use Sequence\reduce;
    use Sequence\remove;
    use Sequence\reverse;
    use Sequence\rotate;
    use Sequence\set;
    use Sequence\shift;
    use Sequence\slice;
    use Sequence\sort;
    use Sequence\sum;
    use Sequence\toArray;
    use Sequence\unshift;

    public function testReallocatingWhenHeadNotAtZero()
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

    public function testReallocatingWhenHeadHasWrapped()
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

    public function testRealignmentOfWrappedBufferWithLargeTempSpace()
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
        $this->assertToArray($expected, $instance->sort(function ($a, $b) {
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
        $this->assertToArray($expected, $instance->sort(function ($a, $b) {
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
        $this->assertToArray($expected, $instance->sort(function ($a, $b) {
            return $b <=> $a; // Reverse
        }));
    }

    protected function getInstance(array $values = [])
    {
        return new \Altair\Structure\Deque($values);
    }
}
