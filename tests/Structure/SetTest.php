<?php
namespace Altair\Tests\Structure;

use Altair\Structure\Set as SetObject;

class SetTest extends AbstractCollectionTest
{
    use Set\__construct;
    use Set\_clone;
    use Set\_echo;
    use Set\_empty;
    use Set\_foreach;
    use Set\_isset;
    use Set\_jsonEncode;
    use Set\_list;
    use Set\_serialize;
    use Set\_unset;
    use Set\_var_dump;

    use Set\add;
    use Set\allocate;
    use Set\capacity;
    use Set\clear;
    use Set\contains;
    use Set\copy;
    use Set\count;
    use Set\diff;
    use Set\filter;
    use Set\first;
    use Set\get;
    use Set\intersect;
    use Set\isEmpty;
    use Set\join;
    use Set\last;
    use Set\merge;
    use Set\reduce;
    use Set\remove;
    use Set\reverse;
    use Set\slice;
    use Set\sort;
    use Set\sum;
    use Set\toArray;
    use Set\union;
    use Set\xor_;

    protected function getInstance(array $values = [])
    {
        return new SetObject($values);
    }

    public function getUniqueAndDuplicateData()
    {
        $sample = $this->sample();
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

    public function testArrayAccessSet()
    {
        $set = $this->getInstance();
        $this->expectOutOfBoundsException();
        $set['a'] = 1;
    }
}
