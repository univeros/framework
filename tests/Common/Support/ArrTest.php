<?php

declare(strict_types=1);

namespace Altair\Tests\Common\Support;

use Altair\Common\Support\Arr;
use PHPUnit\Framework\TestCase;

class ArrTest extends TestCase
{
    public function testMergeAssociativeRecursively(): void
    {
        $result = Arr::merge(
            ['user' => ['name' => 'alice', 'age' => 30]],
            ['user' => ['age' => 31, 'role' => 'admin']],
        );

        $this->assertSame(['user' => ['name' => 'alice', 'age' => 31, 'role' => 'admin']], $result);
    }

    public function testMergeAppendsIntegerKeys(): void
    {
        $result = Arr::merge([0 => 'a'], [0 => 'b']);

        $this->assertSame(['a', 'b'], $result);
    }

    public function testGetValueByFlatKey(): void
    {
        $this->assertSame('alice', Arr::getValue(['name' => 'alice'], 'name'));
    }

    public function testGetValueByDotPath(): void
    {
        $this->assertSame(
            'alice',
            Arr::getValue(['user' => ['name' => 'alice']], 'user.name'),
        );
    }

    public function testGetValueReturnsDefaultWhenMissing(): void
    {
        $this->assertSame('fallback', Arr::getValue([], 'missing', 'fallback'));
    }

    public function testRemoveExtractsValueAndDeletesKey(): void
    {
        $array = ['a' => 1, 'b' => 2];

        $value = Arr::remove($array, 'a');

        $this->assertSame(1, $value);
        $this->assertSame(['b' => 2], $array);
    }

    public function testRemoveReturnsDefaultWhenKeyMissing(): void
    {
        $array = ['a' => 1];

        $this->assertSame('fallback', Arr::remove($array, 'missing', 'fallback'));
    }

    public function testRemoveValueExtractsMatchingValues(): void
    {
        $array = ['a' => 'Dylan', 'b' => 'Jackson', 'c' => 'Jagger', 'd' => 'Jackson'];

        $removed = Arr::removeValue($array, 'Jackson');

        $this->assertSame(['a' => 'Dylan', 'c' => 'Jagger'], $array);
        $this->assertSame(['b' => 'Jackson', 'd' => 'Jackson'], $removed);
    }

    public function testIndexByKey(): void
    {
        $array = [
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
        ];

        $this->assertSame(
            [1 => ['id' => 1, 'name' => 'a'], 2 => ['id' => 2, 'name' => 'b']],
            Arr::index($array, 'id'),
        );
    }

    public function testKeyExistsIsCaseInsensitiveWhenRequested(): void
    {
        $array = ['Name' => 'alice'];

        $this->assertTrue(Arr::keyExists('Name', $array));
        $this->assertFalse(Arr::keyExists('name', $array));
        $this->assertTrue(Arr::keyExists('name', $array, caseSensitive: false));
    }

    public function testIsAssociative(): void
    {
        $this->assertTrue(Arr::isAssociative(['a' => 1, 'b' => 2]));
        $this->assertFalse(Arr::isAssociative([1, 2, 3]));
    }

    public function testIsIndexed(): void
    {
        $this->assertTrue(Arr::isIndexed([1, 2, 3]));
        $this->assertFalse(Arr::isIndexed(['a' => 1]));
        $this->assertTrue(Arr::isIndexed([0 => 'a', 1 => 'b'], consecutive: true));
        $this->assertFalse(Arr::isIndexed([0 => 'a', 2 => 'b'], consecutive: true));
    }

    public function testIsIn(): void
    {
        $this->assertTrue(Arr::isIn('a', ['a', 'b']));
        $this->assertFalse(Arr::isIn('x', ['a', 'b']));
        $this->assertTrue(Arr::isIn('1', [1, 2])); // loose comparison default
        $this->assertFalse(Arr::isIn('1', [1, 2], strict: true));
    }

    public function testIsSubset(): void
    {
        $this->assertTrue(Arr::isSubset(['a'], ['a', 'b', 'c']));
        $this->assertFalse(Arr::isSubset(['x'], ['a', 'b', 'c']));
    }
}
