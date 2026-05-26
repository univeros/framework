<?php
namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Set\__construct;
use Altair\Tests\Structure\Set\_clone;
use Altair\Tests\Structure\Set\_echo;
use Altair\Tests\Structure\Set\_empty;
use Altair\Tests\Structure\Set\_foreach;
use Altair\Tests\Structure\Set\_isset;
use Altair\Tests\Structure\Set\_jsonEncode;
use Altair\Tests\Structure\Set\_list;
use Altair\Tests\Structure\Set\_serialize;
use Altair\Tests\Structure\Set\_unset;
use Altair\Tests\Structure\Set\_var_dump;
use Altair\Tests\Structure\Set\add;
use Altair\Tests\Structure\Set\allocate;
use Altair\Tests\Structure\Set\capacity;
use Altair\Tests\Structure\Set\clear;
use Altair\Tests\Structure\Set\contains;
use Altair\Tests\Structure\Set\copy;
use Altair\Tests\Structure\Set\count;
use Altair\Tests\Structure\Set\diff;
use Altair\Tests\Structure\Set\filter;
use Altair\Tests\Structure\Set\first;
use Altair\Tests\Structure\Set\get;
use Altair\Tests\Structure\Set\intersect;
use Altair\Tests\Structure\Set\isEmpty;
use Altair\Tests\Structure\Set\join;
use Altair\Tests\Structure\Set\last;
use Altair\Tests\Structure\Set\merge;
use Altair\Tests\Structure\Set\reduce;
use Altair\Tests\Structure\Set\remove;
use Altair\Tests\Structure\Set\reverse;
use Altair\Tests\Structure\Set\slice;
use Altair\Tests\Structure\Set\sort;
use Altair\Tests\Structure\Set\sum;
use Altair\Tests\Structure\Set\toArray;
use Altair\Tests\Structure\Set\union;
use Altair\Tests\Structure\Set\xor_;
use Altair\Structure\Set as SetObject;

class SetTest extends AbstractCollectionTest
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

    use add;
    use allocate;
    use capacity;
    use clear;
    use contains;
    use copy;
    use count;
    use diff;
    use filter;
    use first;
    use get;
    use intersect;
    use isEmpty;
    use join;
    use last;
    use merge;
    use reduce;
    use remove;
    use reverse;
    use slice;
    use sort;
    use sum;
    use toArray;
    use union;
    use xor_;

    public static function getUniqueAndDuplicateData(): array
    {
        $sample = static::sample();
        $duplicates = [];

        foreach ($sample as $value) {
            $duplicates[] = $value;
            $duplicates[] = $value;
        }

        $sample[] = new HashableObject(1);

        $duplicates[] = new HashableObject(1);
        $duplicates[] = new HashableObject(1);

        return [$sample, $duplicates];
    }

    public function testArrayAccessSet(): void
    {
        $set = $this->getInstance();
        $this->expectOutOfBoundsException();
        $set['a'] = 1;
    }

    protected static function getInstance(array $values = []): SetObject
    {
        return new SetObject($values);
    }
}
