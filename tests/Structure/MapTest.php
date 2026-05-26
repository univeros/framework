<?php

namespace Altair\Tests\Structure;

use Altair\Tests\Structure\Map\__construct;
use Altair\Tests\Structure\Map\_clone;
use Altair\Tests\Structure\Map\_echo;
use Altair\Tests\Structure\Map\_empty;
use Altair\Tests\Structure\Map\_foreach;
use Altair\Tests\Structure\Map\_isset;
use Altair\Tests\Structure\Map\_jsonEncode;
use Altair\Tests\Structure\Map\_serialize;
use Altair\Tests\Structure\Map\_unset;
use Altair\Tests\Structure\Map\_var_dump;
use Altair\Tests\Structure\Map\allocate;
use Altair\Tests\Structure\Map\apply;
use Altair\Tests\Structure\Map\capacity;
use Altair\Tests\Structure\Map\clear;
use Altair\Tests\Structure\Map\copy;
use Altair\Tests\Structure\Map\count;
use Altair\Tests\Structure\Map\diff;
use Altair\Tests\Structure\Map\filter;
use Altair\Tests\Structure\Map\first;
use Altair\Tests\Structure\Map\get;
use Altair\Tests\Structure\Map\hasKey;
use Altair\Tests\Structure\Map\hasValue;
use Altair\Tests\Structure\Map\intersect;
use Altair\Tests\Structure\Map\isEmpty;
use Altair\Tests\Structure\Map\keys;
use Altair\Tests\Structure\Map\ksort;
use Altair\Tests\Structure\Map\last;
use Altair\Tests\Structure\Map\map;
use Altair\Tests\Structure\Map\merge;
use Altair\Tests\Structure\Map\pairs;
use Altair\Tests\Structure\Map\put;
use Altair\Tests\Structure\Map\putAll;
use Altair\Tests\Structure\Map\reduce;
use Altair\Tests\Structure\Map\remove;
use Altair\Tests\Structure\Map\reverse;
use Altair\Tests\Structure\Map\skip;
use Altair\Tests\Structure\Map\slice;
use Altair\Tests\Structure\Map\sort;
use Altair\Tests\Structure\Map\sum;
use Altair\Tests\Structure\Map\toArray;
use Altair\Tests\Structure\Map\union;
use Altair\Tests\Structure\Map\values;
use Altair\Tests\Structure\Map\xor_;

class MapTest extends AbstractCollectionTest
{
    use __construct;
    use _clone;
    use _echo;
    use _empty;
    use _foreach;
    use _isset;
    use _jsonEncode;
    use _serialize;
    use _unset;
    use _var_dump;

    use allocate;
    use apply;
    use capacity;
    use clear;
    use copy;
    use count;
    use diff;
    use filter;
    use first;
    use get;
    use hasKey;
    use hasValue;
    use intersect;
    use isEmpty;
    use keys;
    use ksort;
    use last;
    use map;
    use merge;
    use pairs;
    use put;
    use putAll;
    use reduce;
    use remove;
    use reverse;
    use skip;
    use slice;
    use sort;
    use sum;
    use toArray;
    use union;
    use values;
    use xor_;

    public function testCollisionChain(): void
    {
        $instance = $this->getInstance();

        // We want to add three distinct values with the same hash mod.
        $instance->put(3, 'a');
        $instance->put(11, 'b');
        $instance->put(19, 'c');

        $this->assertEquals('a', $instance->get(3));
        $this->assertEquals('b', $instance->get(11));
        $this->assertEquals('c', $instance->get(19));
    }

    public function testCollisionChainAcrossResize(): void
    {
        $instance = $this->getInstance();

        $n = 64;

        for ($i = 0; $i < $n; $i++) {
            $instance->put(($i * $n) + 3, $i);
        }

        for ($i = 0; $i < $n; $i++) {
            $this->assertEquals($i, $instance->get(($i * $n) + 3));
        }
    }

    public function testNonPackedRehash(): void
    {
        $instance = $this->getInstance();

        $instance->put(3, 'a');
        $instance->put(11, 'b');
        $instance->put(19, 'c');
        $instance->put(27, 'd');

        $instance->remove(11);
        $instance->remove(27);

        for ($i = 30; $i < 50; $i++) {
            $instance->put($i, $i);
        }

        $this->assertEquals('a', $instance->get(3));
        $this->assertEquals('c', $instance->get(19));

        for ($i = 30; $i < 50; $i++) {
            $this->assertEquals($i, $instance->get($i));
        }
    }

    public function testPutAfterRemove(): void
    {
        $instance = $this->getInstance();
        $instance->put(1, 1);
        $instance->put(2, 2);

        $instance->remove(1);
        $instance->put(1, 1);

        $this->assertToArray([2 => 2, 1 => 1], $instance);
    }

    public function testRandomPutAndRemove(): void
    {
        $instance = $this->getInstance();
        $reference = [];

        for ($i = 0; $i < self::MANY; $i++) {
            $key = random_int(0, $i);
            $val = random_int(0, mt_getrandmax());

            $instance[$key] = $val;
            $reference[$key] = $val;
        }

        for ($i = 0; $i < self::MANY; $i++) {
            $key = random_int(0, $i);

            unset($instance[$key], $reference[$key]);
        }

        foreach ($reference as $key => $value) {
            $this->assertEquals($value, $instance->get($key));
        }
    }

    public function testAlternatingPutAndRemove(): void
    {
        $instance = $this->getInstance();
        $reference = [];

        for ($i = 0; $i < self::MANY; $i++) {
            $key = random_int(0, $i);
            $val = random_int(0, mt_getrandmax());

            if ($i % 2 !== 0) {
                $instance[$key] = $val;
                $reference[$key] = $val;
            } else {
                unset($instance[$key], $reference[$key]);
            }
        }

        foreach ($reference as $key => $value) {
            $this->assertEquals($value, $instance->get($key));
        }
    }

    protected static function getInstance(array $values = []): \Altair\Structure\Map
    {
        return new \Altair\Structure\Map($values);
    }
}
