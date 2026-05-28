<?php
namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Stack\__construct;
use Altair\Tests\Structure\Stack\_clone;
use Altair\Tests\Structure\Stack\_echo;
use Altair\Tests\Structure\Stack\_empty;
use Altair\Tests\Structure\Stack\_foreach;
use Altair\Tests\Structure\Stack\_isset;
use Altair\Tests\Structure\Stack\_jsonEncode;
use Altair\Tests\Structure\Stack\_list;
use Altair\Tests\Structure\Stack\_serialize;
use Altair\Tests\Structure\Stack\_unset;
use Altair\Tests\Structure\Stack\_var_dump;
use Altair\Tests\Structure\Vector\allocate;
use Altair\Tests\Structure\Vector\capacity;
use Altair\Tests\Structure\Stack\clear;
use Altair\Tests\Structure\Stack\copy;
use Altair\Tests\Structure\Stack\count;
use Altair\Tests\Structure\Stack\isEmpty;
use Altair\Tests\Structure\Stack\peek;
use Altair\Tests\Structure\Stack\pop;
use Altair\Tests\Structure\Stack\push;
use Altair\Tests\Structure\Stack\toArray;
use Altair\Structure\Stack as StackObject;
use Altair\Tests\Structure\AbstractCollectionTest;

class StackTest extends AbstractCollectionTest
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

    #[\Override]
    public static function basicDataProvider(): array
    {
        // Stack should produce values in reverse order.
        return array_map(fn(array $data): array => [$data[0], array_reverse($data[1])], parent::basicDataProvider());
    }

    public function serializeDataProvider(): array
    {
        // Stack should serialize in push order, so that it can be
        // unserialized by pushing each serialized value.
        return parent::basicDataProvider();
    }

    public function testArrayAccessSet(): void
    {
        $set = $this->getInstance();
        $this->expectOutOfBoundsException();
        $set['a'] = 1;
    }

    protected static function getInstance(array $values = []): StackObject
    {
        return new StackObject($values);
    }
}
