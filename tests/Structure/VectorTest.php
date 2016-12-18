<?php
namespace Altair\Tests\Structure;

use Altair\Structure\Vector as VectorObject;
use Altair\Tests\Structure\AbstractCollectionTest;

class VectorTest extends AbstractCollectionTest
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

    use Vector\__construct;
    use Vector\allocate;
    use Vector\capacity;

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

    protected function getInstance(array $values = [])
    {
        return new VectorObject($values);
    }
}
