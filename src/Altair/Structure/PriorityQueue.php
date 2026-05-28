<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\CollectionInterface;
use Altair\Structure\Traits\CollectionTrait;
use Altair\Structure\Traits\SquaredCapacityTrait;
use Generator;
use IteratorAggregate;
use Override;
use ReturnTypeWillChange;
use UnderflowException;

/**
 *
 * PriorityQueue.
 *
 * A PriorityQueue is very similar to a Queue. Values are pushed into the queue with an assigned priority, and the
 * value with the highest priority will always be at the front of the queue. Iterating over a PriorityQueue is
 * destructive, equivalent to successive pop operations until the queue is empty. Implemented using a max heap.
 *
 * @link https://medium.com/@rtheunissen/efficient-data-structures-for-php-7-9dda7af674cd#.gl62k1xqr
 *
 * @template TValue
 *
 * @implements CollectionInterface<int, TValue>
 * @implements IteratorAggregate<int, TValue>
 *
 * @phpstan-consistent-constructor
 */
class PriorityQueue implements IteratorAggregate, CollectionInterface
{
    /** @use CollectionTrait<int, TValue> */
    use CollectionTrait;
    use SquaredCapacityTrait;

    /**
     * Typed against the concrete PriorityNode (not the interface) so its public
     * $value/$priority/$stamp properties resolve; the queue only ever stores
     * PriorityNode instances.
     *
     * @var array<int, PriorityNode<TValue>>
     */
    protected $heap = [];

    /**
     * @var int
     */
    protected $stamp = 0;

    /**
     * Initializes a new priority queue.
     *
     * @param array<int, PriorityNode<TValue>>|null $heap
     * @param int|null $stamp
     */
    public function __construct($heap = [], $stamp = 0)
    {
        $this->heap = $heap ?? [];
        $this->stamp = $stamp ?? 0;
    }

    /**
     * {@inheritDoc}
     *
     * @return self<TValue>
     */
    #[Override]
    public function copy(): PriorityQueue
    {
        return new PriorityQueue($this->heap, $this->capacity);
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function count(): int
    {
        return \count($this->heap);
    }

    /**
     * Returns the value with the highest priority without removing it.
     *
     * @return TValue
     */
    public function peek()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Queue is empty');
        }

        return $this->heap[0]->value;
    }

    /**
     * Returns and removes the value with the highest priority in the queue.
     *
     * @return TValue
     */
    public function pop()
    {
        if ($this->heap === []) {
            throw new UnderflowException('Queue is empty');
        }

        // Last leaf of the heap to become the new root.
        $leaf = array_pop($this->heap);

        if ($this->heap === []) {
            return $leaf->value;
        }

        // Cache the current root value to return before replacing with next.
        $value = $this->getRoot()->value;

        // Replace the root, then sift down.
        $this->setRoot($leaf);
        $this->siftDown(0);
        $this->adjustCapacity();

        return $value;
    }

    /**
     * Pushes a value into the queue, with a specified priority.
     *
     * @param TValue $value
     */
    public function push(mixed $value, int $priority): void
    {
        $this->adjustCapacity();

        // Add new leaf, then sift up to maintain heap,
        $this->heap[] = new PriorityNode($value, $priority, $this->stamp++);
        $this->siftUp(\count($this->heap) - 1);
    }

    /**
     * {@inheritDoc}
     *
     * @return array<int, TValue>
     */
    #[Override]
    public function toArray(): array
    {
        $heap = $this->heap;
        $array = [];

        while (!$this->isEmpty()) {
            $array[] = $this->pop();
        }

        $this->heap = $heap;

        return $array;
    }

    /**
     * Get iterator.
     *
     * @return Generator<int, TValue>
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function getIterator()
    {
        while (!$this->isEmpty()) {
            yield $this->pop();
        }
    }

    /**
     * Left.
     *
     *
     */
    protected function left(int $index): int
    {
        return ($index * 2) + 1;
    }

    /**
     * Right.
     *
     *
     */
    protected function right(int $index): int
    {
        return ($index * 2) + 2;
    }

    /**
     * Parent.
     *
     *
     */
    protected function parent(int $index): int
    {
        return (int) (($index - 1) / 2);
    }

    /**
     * Compare.
     *
     *
     */
    protected function compare(int $a, int $b): int
    {
        $x = $this->heap[$a];
        $y = $this->heap[$b];

        // Compare priority, using insertion stamp as fallback.
        return ($x->priority <=> $y->priority) ?: ($y->stamp <=> $x->stamp);
    }

    /**
     * Swap.
     */
    protected function swap(int $a, int $b): void
    {
        $temp = $this->heap[$a];
        $this->heap[$a] = $this->heap[$b];
        $this->heap[$b] = $temp;
    }

    /**
     * Get Largest Leaf.
     *
     *
     */
    protected function getLargestLeaf(int $parent): int
    {
        $left = $this->left($parent);
        $right = $this->right($parent);

        if ($right < \count($this->heap) && $this->compare($left, $right) < 0) {
            return $right;
        }

        return $left;
    }

    /**
     * Sift Up.
     */
    protected function siftUp(int $leaf): void
    {
        for (; $leaf > 0; $leaf = $parent) {
            $parent = $this->parent($leaf);

            // Done when parent priority is greater.
            if ($this->compare($leaf, $parent) < 0) {
                break;
            }

            $this->swap($parent, $leaf);
        }
    }

    /**
     * Set Root.
     *
     * @param PriorityNode<TValue> $node
     */
    protected function setRoot(PriorityNode $node): void
    {
        $this->heap[0] = $node;
    }

    /**
     * Get Root.
     *
     * @return PriorityNode<TValue>
     */
    protected function getRoot(): PriorityNode
    {
        return $this->heap[0];
    }

    /**
     * Sift Down.
     */
    private function siftDown(int $node): void
    {
        $last = floor(\count($this->heap) / 2);

        for ($parent = $node; $parent < $last; $parent = $leaf) {
            // Determine the largest leaf to potentially swap with the parent.
            $leaf = $this->getLargestLeaf($parent);

            // Done if the parent is not greater than its largest leaf
            if ($this->compare($parent, $leaf) > 0) {
                break;
            }

            $this->swap($parent, $leaf);
        }
    }
}
