<?php
namespace Altair\tests\Structure\Sequence;

trait push
{
    public function pushDataProvider()
    {
        return $this->basicDataProvider();
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testPushVariadic(array $values, array $expected)
    {
        $instance = $this->getInstance();
        $instance->push(...$values);

        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testPush(array $values, array $expected)
    {
        $instance = $this->getInstance();

        foreach ($values as $value) {
            $instance->push($value);
        }

        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    /**
     * @dataProvider pushDataProvider
     */
    public function testArrayAccessPush(array $values, array $expected)
    {
        $instance = $this->getInstance();

        foreach ($values as $value) {
            $instance[] = $value;
        }

        $this->assertToArray($expected, $instance);
        $this->assertEquals(count($expected), count($instance));
    }

    public function testPushCircularReference()
    {
        $instance = $this->getInstance(['a', 'b', 'c']);
        $instance->push($instance);
        $this->assertToArray(['a', 'b', 'c', $instance], $instance);
    }

    public function testPushIndirectCircularReference()
    {
        $a = $this->getInstance();
        $b = $this->getInstance();

        $a->push($b);
        $b->push($a);

        $this->assertToArray([$b], $a);
        $this->assertToArray([$a], $b);
    }

    public function testPushDeeperIndirectCircularReference()
    {
        $a = $this->getInstance();
        $b = $this->getInstance();

        $a->push($b);
        $b->push($a);

        $a->push($a);
        $b->push($b);

        $a->push($b);
        $b->push($a);

        $this->assertToArray([$b, $a, $b], $a);
        $this->assertToArray([$a, $b, $a], $b);
    }

    public function testPushIndirectCircularReferenceAfterUnshifts()
    {
        $a = $this->getInstance();
        $b = $this->getInstance();

        $a->push(...range(1, 5));
        $b->push(...range(1, 5));

        $a->unshift($b);
        $b->unshift($a);

        $this->assertToArray([$b, 1, 2, 3, 4, 5], $a);
        $this->assertToArray([$a, 1, 2, 3, 4, 5], $b);
    }
}
