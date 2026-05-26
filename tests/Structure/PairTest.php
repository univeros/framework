<?php

namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Pair\__construct;
use Altair\Tests\Structure\Pair\__get;
use Altair\Tests\Structure\Pair\__set;
use Altair\Tests\Structure\Pair\_clone;
use Altair\Tests\Structure\Pair\_echo;
use Altair\Tests\Structure\Pair\_empty;
use Altair\Tests\Structure\Pair\_isset;
use Altair\Tests\Structure\Pair\_jsonEncode;
use Altair\Tests\Structure\Pair\_list;
use Altair\Tests\Structure\Pair\_serialize;
use Altair\Tests\Structure\Pair\_unset;
use Altair\Tests\Structure\Pair\_var_dump;
use Altair\Tests\Structure\Pair\copy;
use Altair\Tests\Structure\Pair\toArray;
use Altair\Structure\Pair as PairObject;

class PairTest extends AbstractCollectionTest
{
    use __construct;
    use __get;
    use __set;
    use _clone;
    use _echo;
    use _empty;
    use _isset;
    use _jsonEncode;
    use _list;
    use _serialize;
    use _unset;
    use _var_dump;
    use copy;
    use toArray;

    private function getPair($key, $value): PairObject
    {
        return new PairObject($key, $value);
    }
}
