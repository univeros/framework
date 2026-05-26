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
use Altair\Tests\Structure\Vector\__construct;
use Altair\Tests\Structure\Vector\allocate;
use Altair\Tests\Structure\Vector\capacity;
use Altair\Tests\Structure\Sequence\apply;
use Altair\Tests\Structure\Sequence\clear;
use Altair\Tests\Structure\Sequence\contains;
use Altair\Tests\Structure\Sequence\copy;
use Altair\Tests\Structure\Sequence\count;
use Altair\Tests\Structure\Sequence\filter;
use Altair\Tests\Structure\Sequence\find;
use Altair\Tests\Structure\Sequence\first;
use Altair\Tests\Structure\Sequence\get;
use Altair\Tests\Structure\Sequence\insert;
use Altair\Tests\Structure\Sequence\isEmpty;
use Altair\Tests\Structure\Sequence\join;
use Altair\Tests\Structure\Sequence\last;
use Altair\Tests\Structure\Sequence\map;
use Altair\Tests\Structure\Sequence\merge;
use Altair\Tests\Structure\Sequence\pop;
use Altair\Tests\Structure\Sequence\push;
use Altair\Tests\Structure\Sequence\reduce;
use Altair\Tests\Structure\Sequence\remove;
use Altair\Tests\Structure\Sequence\reverse;
use Altair\Tests\Structure\Sequence\rotate;
use Altair\Tests\Structure\Sequence\set;
use Altair\Tests\Structure\Sequence\shift;
use Altair\Tests\Structure\Sequence\slice;
use Altair\Tests\Structure\Sequence\sort;
use Altair\Tests\Structure\Sequence\sum;
use Altair\Tests\Structure\Sequence\toArray;
use Altair\Tests\Structure\Sequence\unshift;
use Altair\Structure\Vector as VectorObject;
use Altair\Tests\Structure\AbstractCollectionTest;

class VectorTest extends AbstractCollectionTest
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

    use apply;
    use clear;
    use contains;
    use copy;
    use count;
    use filter;
    use find;
    use first;
    use get;
    use insert;
    use isEmpty;
    use join;
    use last;
    use map;
    use merge;
    use pop;
    use push;
    use reduce;
    use remove;
    use reverse;
    use rotate;
    use set;
    use shift;
    use slice;
    use sort;
    use sum;
    use toArray;
    use unshift;

    protected static function getInstance(array $values = []): VectorObject
    {
        return new VectorObject($values);
    }
}
