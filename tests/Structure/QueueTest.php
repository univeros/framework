<?php

namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Queue\__construct;
use Altair\Tests\Structure\Queue\_clone;
use Altair\Tests\Structure\Queue\_echo;
use Altair\Tests\Structure\Queue\_empty;
use Altair\Tests\Structure\Queue\_foreach;
use Altair\Tests\Structure\Queue\_isset;
use Altair\Tests\Structure\Queue\_jsonEncode;
use Altair\Tests\Structure\Queue\_list;
use Altair\Tests\Structure\Queue\_serialize;
use Altair\Tests\Structure\Queue\_unset;
use Altair\Tests\Structure\Queue\_var_dump;
use Altair\Tests\Structure\Deque\allocate;
use Altair\Tests\Structure\Deque\capacity;
use Altair\Tests\Structure\Queue\clear;
use Altair\Tests\Structure\Queue\copy;
use Altair\Tests\Structure\Queue\count;
use Altair\Tests\Structure\Queue\isEmpty;
use Altair\Tests\Structure\Queue\peek;
use Altair\Tests\Structure\Queue\pop;
use Altair\Tests\Structure\Queue\push;
use Altair\Tests\Structure\Queue\toArray;
use Altair\Structure\Queue as QueueObject;

class QueueTest extends AbstractCollectionTest
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

    public function testArrayAccessSet(): void
    {
        $set = $this->getInstance();
        $this->expectOutOfBoundsException();
        $set['a'] = 1;
    }

    protected static function getInstance(array $values = []): QueueObject
    {
        return new QueueObject($values);
    }
}
