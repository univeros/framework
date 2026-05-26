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
    public static function sample(): array
    {
        return array_merge(
            [[], null],
            ['#', '1', 1, 1.0, true],
            ['', '0', 0, 0.0, false],
            ['a', 'A', new \stdClass()],
            range(2, self::SOME)
        );
    }

    public function assertInstanceToString($instance): void
    {
        $this->assertEquals('object(' . $instance::class . ')', $instance);
    }

    public function assertToArray(array $expected, $instance): void
    {
        $actual = $instance->toArray();
        // We have to make separate assertions here because PHPUnit considers an
        // array to be equal of the keys match the values even if the order is
        // not the same, ie. [a => 1, b => 2] equals [b => 2, a => 1].
        $this->assertEquals(array_values($expected), array_values($actual), '!!! ARRAY VALUE MISMATCH');
        $this->assertEquals(array_keys($expected), array_keys($actual), '!!! ARRAY KEY MISMATCH');
    }

    public static function basicDataProvider(): array
    {
        $sample = static::sample();
        $values = [
            [],
            ['a'],
            ['a', 'b'],
            ['a', 'b', 'c'],
            $sample,
        ];

        return array_map(
            fn($a): array => [$a, $a],
            $values
        );
    }

    public function expectAccessByReferenceHasNoEffect(): void
    {
        $this->markTestSkipped('PHPUnit 10+ removed PHPUnit\\Framework\\Error\\Notice; reference-access semantics are PHP-version dependent.');
    }

    public function expectPropertyDoesNotExistException(): void
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectReconstructionNotAllowedException(): void
    {
        $this->expectException('Error');
    }

    public function expectImmutableException(): void
    {
        $this->expectException('Error');
    }

    public function expectAccessByReferenceNotAllowedException(): void
    {
        $this->expectException('Error');
    }

    public function expectListNotSupportedException(): void
    {
        $this->expectException('Error');
    }

    public function expectIterateByReferenceException(): void
    {
        $this->expectException('Error');
    }

    public function expectWrongIndexTypeException(): void
    {
        $this->expectException('TypeError');
    }

    public function expectOutOfBoundsException(): void
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectArrayAccessUnsupportedException(): void
    {
        $this->expectException('Error');
    }

    public function expectKeyNotFoundException(): void
    {
        $this->expectException(\OutOfBoundsException::class);
    }

    public function expectIndexOutOfRangeException(): void
    {
        $this->expectException(\OutOfRangeException::class);
    }

    public function expectEmptyNotAllowedException(): void
    {
        $this->expectException(\UnderflowException::class);
    }

    public function expectNotIterableOrArrayException(): void
    {
        $this->expectException('TypeError');
    }

    public function expectInternalIllegalOffset(): void
    {
        $this->markTestSkipped('PHPUnit 10+ removed PHPUnit\\Framework\\Error\\Warning; illegal-offset semantics are PHP-version dependent.');
    }

    public static function outOfRangeDataProvider(): array
    {
        return [
            [[], -1],
            [[], 1],
            [[1], 2],
            [[1], -1],
        ];
    }

    public static function badIndexDataProvider(): array
    {
        return [
            [[], 'a'],
        ];
    }

    public function assertInstanceDump(array $expected, $instance): void
    {
        ob_start();
        $this->cleanVarDump($instance);
        $actual = ob_get_clean();
        ob_start();
        $this->cleanVarDump($expected);
        $expected = ob_get_clean();
        $class = preg_quote($instance::class);
        $data = preg_quote(substr($expected, 5)); // Slice past 'array'
        $regex = preg_replace('/#\d+/', '#\d+', sprintf('object\(%s\)#\d+ %s', $class, $data));
        $this->assertMatchesRegularExpression(sprintf('~%s~', $regex), $actual);
    }

    public function assertSerialized(array $expected, $instance, $use_keys): void
    {
        $unserialized = unserialize(serialize($instance));
        // Check that the instance is an instance of the correct class and that
        // its values reflect the original values.
        $this->assertEquals($instance::class, $unserialized::class);
        $this->assertEquals($instance->toArray(), $unserialized->toArray());
        $this->assertTrue($instance !== $unserialized);
    }

    public function assertForEach(array $expected, $instance): void
    {
        $data = [];
        foreach ($instance as $key => $value) {
            $data[$key] = $value;
        }

        $this->assertEquals($expected, $data);
    }

    public function assertForEachByReferenceThrowsException($instance): void
    {
        $this->expectIterateByReferenceException();
    }

    /**
     * Perform a clean var dump disabling xdebug overload if set.
     */
    protected function cleanVarDump(mixed $expression)
    {
        $overload_var_dump = ini_get('xdebug.overload_var_dump');
        ini_set('xdebug.overload_var_dump', 'off');
        var_dump($expression);
        ini_set('xdebug.overload_var_dump', $overload_var_dump);
    }
}
