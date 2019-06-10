<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\CollectionInterface;
use Altair\Structure\Contracts\PriorityNodeInterface;
use IteratorAggregate;
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
 */
class PriorityQueue implements IteratorAggregate, CollectionInterface
{
    use Traits\CollectionTrait;
    use Traits\SquaredCapacityTrait;

    /**
     * @var PriorityNodeInterface[]
     */
    protected $heap = [];

    protected $stamp = 0;

    /**
     * Initializes a new priority queue.
     *
     * @param array|null $heap
     * @param int|null $stamp
     */
    public function __construct($heap = [], $stamp = 0)
    {
        $this->heap = $heap ?? [];
        $this->stamp = $stamp ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function copy()
    {
        return new PriorityQueue($this->heap, $this->capacity);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->heap);
    }

    /**
     * {@inheritDoc}
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
     * @return mixed
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            throw new UnderflowException('Queue is empty');
        }

        // Last leaf of the heap to become the new root.
        $leaf = array_pop($this->heap);

        if (empty($this->heap)) {
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
     * @param mixed $value
     * @param int $priority
     */
    public function push($value, int $priority)
    {
        $this->adjustCapacity();

        // Add new leaf, then sift up to maintain heap,
        $this->heap[] = new PriorityNode($value, $priority, $this->stamp++);
        $this->siftUp(count($this->heap) - 1);
    }

    /**
     * {@inheritDoc}
     */
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
     */
    public function getIterator()
    {
        while (!$this->isEmpty()) {
            yield $this->pop();
        }
    }

    /**
     * Left.
     *
     * @param int $index
     *
     * @return int
     */
    protected function left(int $index): int
    {
        return ($index * 2) + 1;
    }

    /**
     * Right.
     *
     * @param int $index
     *
     * @return int
     */
    protected function right(int $index): int
    {
        return ($index * 2) + 2;
    }

    /**
     * Parent.
     *
     * @param int $index
     *
     * @return int
     */
    protected function parent(int $index): int
    {
        return (int)(($index - 1) / 2);
    }

    /**
     * Compare.
     *
     * @param int $a
     * @param int $b
     *
     * @return int
     */
    protected function compare(int $a, int $b)
    {
        $x = $this->heap[$a];
        $y = $this->heap[$b];

        // Compare priority, using insertion stamp as fallback.
        return ($x->priority <=> $y->priority) ?: ($y->stamp <=> $x->stamp);
    }

    /**
     * Swap.
     *
     * @param int $a
     * @param int $b
     */
    protected function swap(int $a, int $b)
    {
        $temp = $this->heap[$a];
        $this->heap[$a] = $this->heap[$b];
        $this->heap[$b] = $temp;
    }

    /**
     * Get Largest Leaf.
     *
     * @param int $parent
     *
     * @return int
     */
    protected function getLargestLeaf(int $parent)
    {
        $left = $this->left($parent);
        $right = $this->right($parent);

        if ($right < count($this->heap) && $this->compare($left, $right) < 0) {
            return $right;
        }

        return $left;
    }

    /**
     * Sift Up.
     *
     * @param int $leaf
     */
    protected function siftUp(int $leaf)
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
     * @param PriorityNodeInterface $node
     */
    protected function setRoot(PriorityNodeInterface $node)
    {
        $this->heap[0] = $node;
    }

    /**
     * Get Root.
     *
     * @return PriorityNodeInterface
     */
    protected function getRoot(): PriorityNodeInterface
    {
        return $this->heap[0];
    }

    /**
     * Sift Down.
     *
     * @param int $node
     */
    private function siftDown(int $node)
    {
        $last = floor(count($this->heap) / 2);

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
