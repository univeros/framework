<?php

namespace Altair\Tests\Structure;

class MapTest extends AbstractCollectionTest
{
    use Map\__construct;
    use Map\_clone;
    use Map\_echo;
    use Map\_empty;
    use Map\_foreach;
    use Map\_isset;
    use Map\_jsonEncode;
    use Map\_serialize;
    use Map\_unset;
    use Map\_var_dump;

    use Map\allocate;
    use Map\apply;
    use Map\capacity;
    use Map\clear;
    use Map\copy;
    use Map\count;
    use Map\diff;
    use Map\filter;
    use Map\first;
    use Map\get;
    use Map\hasKey;
    use Map\hasValue;
    use Map\intersect;
    use Map\isEmpty;
    use Map\keys;
    use Map\ksort;
    use Map\last;
    use Map\map;
    use Map\merge;
    use Map\pairs;
    use Map\put;
    use Map\putAll;
    use Map\reduce;
    use Map\remove;
    use Map\reverse;
    use Map\skip;
    use Map\slice;
    use Map\sort;
    use Map\sum;
    use Map\toArray;
    use Map\union;
    use Map\values;
    use Map\xor_;

    protected function getInstance(array $values = [])
    {
        return new \Altair\Structure\Map($values);
    }

    public function testCollisionChain()
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

    public function testCollisionChainAcrossResize()
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

    public function testNonPackedRehash()
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

    public function testPutAfterRemove()
    {
        $instance = $this->getInstance();
        $instance->put(1, 1);
        $instance->put(2, 2);

        $instance->remove(1);
        $instance->put(1, 1);

        $this->assertToArray([2 => 2, 1 => 1], $instance);
    }

    public function testRandomPutAndRemove()
    {
        $instance = $this->getInstance();
        $reference = [];

        for ($i = 0; $i < self::MANY; $i++) {
            $key = rand(0, $i);
            $val = rand();

            $instance[$key] = $val;
            $reference[$key] = $val;
        }

        for ($i = 0; $i < self::MANY; $i++) {
            $key = rand(0, $i);

            unset($instance[$key]);
            unset($reference[$key]);
        }

        foreach ($reference as $key => $value) {
            $this->assertEquals($value, $instance->get($key));
        }
    }

    public function testAlternatingPutAndRemove()
    {
        $instance = $this->getInstance();
        $reference = [];

        for ($i = 0; $i < self::MANY; $i++) {
            $key = rand(0, $i);
            $val = rand();

            if ($i % 2) {
                $instance[$key] = $val;
                $reference[$key] = $val;
            } else {
                unset($instance[$key]);
                unset($reference[$key]);
            }
        }

        foreach ($reference as $key => $value) {
            $this->assertEquals($value, $instance->get($key));
        }
    }
}
