<?php

namespace Altair\Tests\Structure;

use Altair\Tests\Structure\PriorityQueue\__construct;
use Altair\Tests\Structure\PriorityQueue\_clone;
use Altair\Tests\Structure\PriorityQueue\_echo;
use Altair\Tests\Structure\PriorityQueue\_empty;
use Altair\Tests\Structure\PriorityQueue\_foreach;
use Altair\Tests\Structure\PriorityQueue\_isset;
use Altair\Tests\Structure\PriorityQueue\_jsonEncode;
use Altair\Tests\Structure\PriorityQueue\_list;
use Altair\Tests\Structure\PriorityQueue\_serialize;
use Altair\Tests\Structure\PriorityQueue\_unset;
use Altair\Tests\Structure\PriorityQueue\_var_dump;
use Altair\Tests\Structure\PriorityQueue\allocate;
use Altair\Tests\Structure\PriorityQueue\capacity;
use Altair\Tests\Structure\PriorityQueue\clear;
use Altair\Tests\Structure\PriorityQueue\copy;
use Altair\Tests\Structure\PriorityQueue\count;
use Altair\Tests\Structure\PriorityQueue\isEmpty;
use Altair\Tests\Structure\PriorityQueue\peek;
use Altair\Tests\Structure\PriorityQueue\pop;
use Altair\Tests\Structure\PriorityQueue\push;
use Altair\Tests\Structure\PriorityQueue\toArray;
use Altair\Structure\PriorityQueue as PriorityQueueObject;

class PriorityQueueTest extends AbstractCollectionTest
{
    use __construct;
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

    use allocate;
    use capacity;
    use clear;
    use copy;
    use count;
    use isEmpty;
    use peek;
    use pop;
    use push;
    use toArray;

    public static function getInstance(array $values = []): PriorityQueueObject
    {
        $queue = new PriorityQueueObject();

        foreach ($values as $value => $priority) {
            $queue->push($value, $priority);
        }

        return $queue;
    }

    #[\Override]
    public static function basicDataProvider(): array
    {
        return [
            [[], []],
            [['a' => 1],           ['a']],
            [['a' => 1, 'b' => 2], ['b', 'a']],
            [['a' => 2, 'b' => 1], ['a', 'b']],
        ];
    }
}
