<?php

namespace Altair\Tests\Structure;

use PHPUnit\Framework\TestCase;

abstract class AbstractCollectionTest extends TestCase
{
    /**
     * Sample sizes.
     */
    const MANY = 1 << 6 + 1;
    const SOME = 1 << 4 + 1;

    /**
     * Generic mixed value sample array.
     */
    public function sample()
    {
        return array_merge(
            [[], null],
            ['#', '1', 1, 1.0, true],
            ['', '0', 0, 0.0, false],
            ['a', 'A', new \stdClass()],
            range(2, self::SOME)
        );
    }

    public function assertInstanceToString($instance)
    {
        $this->assertEquals('object(' . get_class($instance) . ')', "$instance");
    }

    public function assertToArray(array $expected, $instance)
    {
        $actual = $instance->toArray();
        // We have to make separate assertions here because PHPUnit considers an
        // array to be equal of the keys match the values even if the order is
        // not the same, ie. [a => 1, b => 2] equals [b => 2, a => 1].
        $this->assertEquals(array_values($expected), array_values($actual), '!!! ARRAY VALUE MISMATCH');
        $this->assertEquals(array_keys($expected), array_keys($actual), '!!! ARRAY KEY MISMATCH');
    }

    public function basicDataProvider()
    {
        $sample = $this->sample();
        $values = [
            [],
            ['a'],
            ['a', 'b'],
            ['a', 'b', 'c'],
            $sample,
        ];

        return array_map(
            function ($a) {
                return [$a, $a];
            },
            $values
        );
    }

    public function expectAccessByReferenceHasNoEffect()
    {
        $this->expectException(\PHPUnit_Framework_Error_Notice::class);
    }

    public function expectPropertyDoesNotExistException()
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectReconstructionNotAllowedException()
    {
        $this->expectException('Error');
    }

    public function expectImmutableException()
    {
        $this->expectException('Error');
    }

    public function expectAccessByReferenceNotAllowedException()
    {
        $this->expectException('Error');
    }

    public function expectListNotSupportedException()
    {
        $this->expectException('Error');
    }

    public function expectIterateByReferenceException()
    {
        $this->expectException('Error');
    }

    public function expectWrongIndexTypeException()
    {
        $this->expectException('TypeError');
    }

    public function expectOutOfBoundsException()
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectArrayAccessUnsupportedException()
    {
        $this->expectException('Error');
    }

    public function expectKeyNotFoundException()
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectIndexOutOfRangeException()
    {
        $this->expectException(\OutOfRangeException::class);
    }

    public function expectEmptyNotAllowedException()
    {
        $this->expectException(\UnderflowException::class);
    }

    public function expectNotIterableOrArrayException()
    {
        $this->expectException('TypeError');
    }

    public function expectInternalIllegalOffset()
    {
        $this->expectException(\PHPUnit_Framework_Error_Warning::class);
    }

    public function outOfRangeDataProvider()
    {
        return [
            [[], -1],
            [[], 1],
            [[1], 2],
            [[1], -1],
        ];
    }

    public function badIndexDataProvider()
    {
        return [
            [[], 'a'],
        ];
    }

    public function assertInstanceDump(array $expected, $instance)
    {
        ob_start();
        $this->cleanVarDump($instance);
        $actual = ob_get_clean();
        ob_start();
        $this->cleanVarDump($expected);
        $expected = ob_get_clean();
        $class = preg_quote(get_class($instance));
        $data = preg_quote(substr($expected, 5)); // Slice past 'array'
        $regex = preg_replace('/#\d+/', '#\d+', "object\($class\)#\d+ $data");
        $this->assertRegExp("~$regex~", $actual);
    }

    public function assertSerialized(array $expected, $instance, $use_keys)
    {
        $unserialized = unserialize(serialize($instance));
        // Check that the instance is an instance of the correct class and that
        // its values reflect the original values.
        $this->assertEquals(get_class($instance), get_class($unserialized));
        $this->assertEquals($instance->toArray(), $unserialized->toArray());
        $this->assertTrue($instance !== $unserialized);
    }

    public function assertForEach(array $expected, $instance)
    {
        $data = [];
        foreach ($instance as $key => $value) {
            $data[$key] = $value;
        }
        $this->assertEquals($expected, $data);
    }

    public function assertForEachByReferenceThrowsException($instance)
    {
        $this->expectIterateByReferenceException();
        foreach ($instance as &$value) {
            ;
        }
    }

    /**
     * Perform a clean var dump disabling xdebug overload if set.
     *
     * @param mixed $expression
     */
    protected function cleanVarDump($expression)
    {
        $overload_var_dump = ini_get('xdebug.overload_var_dump');
        ini_set('xdebug.overload_var_dump', 'off');
        var_dump($expression);
        ini_set('xdebug.overload_var_dump', $overload_var_dump);
    }
}
