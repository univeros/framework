<?php
namespace Altair\tests\Structure\Deque;

trait insert
{
    /**
     * Deque has a few edge cases that don't exist for other sequences. These
     * occur when the head of the deque wraps around, ie. h > t.
     */
    public function testInsertExtended()
    {
        $instance = $this->getInstance();

        // The head of the deque will wrap around if all items are unshifted.

        $instance->unshift('c'); // [_, _, _, _, _, _, _, c] tail = 0, head = 7
        $instance->unshift('b'); // [_, _, _, _, _, _, b, c] tail = 0, head = 6
        $instance->unshift('a'); // [_, _, _, _, _, a, b, c] tail = 0, head = 5

        $instance->insert(1, 'x'); // [_, _, _, _, a, x, b, c]
        // $this->assertToArray(['a', 'x', 'b', 'c'], $instance);

        $instance->insert(0, 'y'); // [_, _, _, y, a, x, b, c]
        // $this->assertToArray(['y', 'a', 'x', 'b', 'c'], $instance);

        $instance->insert(4, 'z'); // [_, _, y, a, x, b, z, c]
        // $this->assertToArray(['y', 'a', 'x', 'b', 'z', 'c'], $instance);

        $instance->insert(6, '#'); // [#, _, y, a, x, b, z, c]
        $this->assertToArray(['y', 'a', 'x', 'b', 'z', 'c', '#'], $instance);
    }

    public function testInsertingIntoAnIsland()
    {
        $instance = $this->getInstance();

        // It's possible that the head of the deque comes before the tail, but
        // is not at zero. This could overflow the buffer.

        $instance->push('a', 'b', 'c', 'd', 'e', 'f');
        // [a, b, c, d, e, f, _, _]

        $instance->shift();
        $instance->shift();
        // [_, _, c, d, e, f, _, _]

        $instance->insert(2, 'g', 'h', 'i');
        // [_, _, c, d, e, f, _, _]
        //              ^
        //              [g, h, i]
        //
        // [_, _, c, d, g, h, i, e] f, _, _
        //                          ^  ^  ^
        //
        // The overflow here is likely because we have to move the entire
        // either to the left or the right, rather than a partition.
        $this->assertToArray(['c', 'd', 'g', 'h', 'i', 'e', 'f'], $instance);
    }

    public function testInsertAtBoundaryWithMoreOnTheLeft()
    {
        $instance = $this->getInstance();
        $instance->push(3, 4, 5, 6);
        $instance->unshift(1, 2);

        // [3, 4, 5, 6, _, _, 1, 2]
        //
        // Inserting at index 2 should determine that the right partition
        // should be moved to the left instead of the left to the right.
        $instance->insert(2, 'x');

        $this->assertToArray([1, 2, 'x', 3, 4, 5, 6], $instance);
    }

    public function testInsertAtBoundaryWithMoreOnTheRight()
    {
        $instance = $this->getInstance();
        $instance->push(5, 6);
        $instance->unshift(1, 2, 3, 4);

        // [5, 6, _, _, 1, 2, 3, 4]
        //
        // Inserting at index 4 should determine that the left partition
        // should be moved to the right instead of the right to the left.
        $instance->insert(4, 'x');

        $this->assertToArray([1, 2, 3, 4, 'x', 5, 6], $instance);
    }

    public function testInsertAtBoundaryWithEqualOnBothSides()
    {
        $instance = $this->getInstance();
        $instance->push(4, 5, 6);
        $instance->unshift(1, 2, 3);

        // [4, 5, 6, _, _, 1, 2, 3]
        //
        // Inserting at index 3 should choose to move either partition.
        $instance->insert(3, 'x');

        $this->assertToArray([1, 2, 3, 'x', 4, 5, 6], $instance);
    }

    public function testAlmostFullInsertAtZero()
    {
        $instance = $this->getInstance();
        $instance->push(...range(1, 6));
        $instance->insert(0, 0);

        $this->assertToArray(range(0, 6), $instance);
    }
}
