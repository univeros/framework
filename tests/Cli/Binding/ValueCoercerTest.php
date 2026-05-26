<?php

declare(strict_types=1);

namespace Altair\Tests\Cli\Binding;

use Altair\Cli\Binding\ValueCoercer;
use Altair\Cli\Exception\ValueCoercionException;
use Altair\Tests\Cli\Fixture\Priority;
use Altair\Tests\Cli\Fixture\Role;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionNamedType;

class ValueCoercerTest extends TestCase
{
    private ValueCoercer $coercer;

    protected function setUp(): void
    {
        $this->coercer = new ValueCoercer();
    }

    public function testStringPassthrough(): void
    {
        $type = $this->typeFor(static fn (string $v): string => $v);
        $this->assertSame('hello', $this->coercer->coerce('hello', $type, 'v'));
    }

    public function testIntCoercion(): void
    {
        $type = $this->typeFor(static fn (int $v): int => $v);
        $this->assertSame(42, $this->coercer->coerce('42', $type, 'v'));
        $this->assertSame(-7, $this->coercer->coerce('-7', $type, 'v'));
    }

    public function testIntCoercionRejectsNonInteger(): void
    {
        $this->expectException(ValueCoercionException::class);

        $type = $this->typeFor(static fn (int $v): int => $v);
        $this->coercer->coerce('not-an-int', $type, 'v');
    }

    public function testFloatCoercion(): void
    {
        $type = $this->typeFor(static fn (float $v): float => $v);
        $this->assertSame(1.5, $this->coercer->coerce('1.5', $type, 'v'));
    }

    #[DataProvider('boolTrueProvider')]
    public function testBoolCoercionTruthyValues(mixed $value): void
    {
        $type = $this->typeFor(static fn (bool $v): bool => $v);
        $this->assertTrue($this->coercer->coerce($value, $type, 'v'));
    }

    #[DataProvider('boolFalseProvider')]
    public function testBoolCoercionFalsyValues(mixed $value): void
    {
        $type = $this->typeFor(static fn (bool $v): bool => $v);
        $this->assertFalse($this->coercer->coerce($value, $type, 'v'));
    }

    public function testBoolCoercionRejectsGarbage(): void
    {
        $this->expectException(ValueCoercionException::class);

        $type = $this->typeFor(static fn (bool $v): bool => $v);
        $this->coercer->coerce('maybe', $type, 'v');
    }

    public function testDateTimeImmutableCoercion(): void
    {
        $type = $this->typeFor(static fn (DateTimeImmutable $v): DateTimeImmutable => $v);
        $value = $this->coercer->coerce('2026-05-26T10:00:00Z', $type, 'v');

        $this->assertInstanceOf(DateTimeImmutable::class, $value);
        $this->assertSame('2026-05-26T10:00:00+00:00', $value->format('c'));
    }

    public function testDateTimeImmutableRejectsInvalidString(): void
    {
        $this->expectException(ValueCoercionException::class);

        $type = $this->typeFor(static fn (DateTimeImmutable $v): DateTimeImmutable => $v);
        $this->coercer->coerce('not a date', $type, 'v');
    }

    public function testStringBackedEnumCoercion(): void
    {
        $type = $this->typeFor(static fn (Role $v): Role => $v);
        $this->assertSame(Role::Admin, $this->coercer->coerce('admin', $type, 'v'));
    }

    public function testIntBackedEnumCoercion(): void
    {
        $type = $this->typeFor(static fn (Priority $v): Priority => $v);
        $this->assertSame(Priority::High, $this->coercer->coerce('3', $type, 'v'));
    }

    public function testBackedEnumRejectsUnknownCase(): void
    {
        $this->expectException(ValueCoercionException::class);

        $type = $this->typeFor(static fn (Role $v): Role => $v);
        $this->coercer->coerce('bogus', $type, 'v');
    }

    public function testArrayCoercionFromCommaSeparated(): void
    {
        $type = $this->typeFor(static fn (array $v): array => $v);
        $this->assertSame(['a', 'b', 'c'], $this->coercer->coerce('a,b,c', $type, 'v'));
    }

    public function testArrayCoercionFromArray(): void
    {
        $type = $this->typeFor(static fn (array $v): array => $v);
        $this->assertSame(['a', 'b'], $this->coercer->coerce(['a', 'b'], $type, 'v'));
    }

    public function testNullableAllowsNull(): void
    {
        $type = $this->typeFor(static fn (?string $v): ?string => $v);
        $this->assertNull($this->coercer->coerce(null, $type, 'v'));
    }

    public function testNonNullableRejectsNull(): void
    {
        $this->expectException(ValueCoercionException::class);

        $type = $this->typeFor(static fn (string $v): string => $v);
        $this->coercer->coerce(null, $type, 'v');
    }

    /**
     * @return iterable<array{0: mixed}>
     */
    public static function boolTrueProvider(): iterable
    {
        yield ['true'];
        yield ['1'];
        yield ['yes'];
        yield ['on'];
        yield [true];
        yield [1];
    }

    /**
     * @return iterable<array{0: mixed}>
     */
    public static function boolFalseProvider(): iterable
    {
        yield ['false'];
        yield ['0'];
        yield ['no'];
        yield ['off'];
        yield [false];
        yield [0];
    }

    private function typeFor(callable $callable): ReflectionNamedType
    {
        $params = (new ReflectionFunction($callable))->getParameters();
        $type = $params[0]->getType();
        $this->assertInstanceOf(ReflectionNamedType::class, $type);

        return $type;
    }
}
