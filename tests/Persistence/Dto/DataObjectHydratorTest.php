<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Persistence\Dto;

use Altair\Persistence\Dto\DataObjectHydrator;
use Altair\Persistence\Exception\HydrationException;
use Altair\Persistence\Exception\PersistenceExceptionInterface;
use Altair\Tests\Persistence\Dto\Fixture\AddressDto;
use Altair\Tests\Persistence\Dto\Fixture\ProfileDto;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DataObjectHydrator::class)]
final class DataObjectHydratorTest extends TestCase
{
    private DataObjectHydrator $hydrator;

    protected function setUp(): void
    {
        $this->hydrator = new DataObjectHydrator();
    }

    public function testCoercesScalarStringRowToTypedProperties(): void
    {
        // A row as a database driver would return it: everything stringy.
        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => '42',
            'name' => 'Vega',
            'active' => '1',
            'score' => '3.5',
        ]);

        $this->assertInstanceOf(ProfileDto::class, $dto);
        $this->assertSame(42, $dto->id);
        $this->assertSame('Vega', $dto->name);
        $this->assertTrue($dto->active);
        $this->assertSame(3.5, $dto->score);
    }

    public function testCoercesDateStringToDateTimeImmutable(): void
    {
        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => 1,
            'created_at' => '2026-01-15 09:00:00',
        ]);

        $this->assertInstanceOf(DateTimeImmutable::class, $dto->created_at);
        $this->assertSame('2026-01-15 09:00:00', $dto->created_at->format('Y-m-d H:i:s'));
    }

    public function testHydratesNestedDataObjectFromArray(): void
    {
        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => 1,
            'address' => ['city' => 'New York', 'zip' => '10001'],
        ]);

        $this->assertInstanceOf(AddressDto::class, $dto->address);
        $this->assertSame('New York', $dto->address->city);
        $this->assertSame('10001', $dto->address->zip);
    }

    public function testNullValuesArePreserved(): void
    {
        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => 1,
            'score' => null,
            'created_at' => null,
        ]);

        $this->assertNull($dto->score);
        $this->assertNull($dto->created_at);
    }

    public function testUnknownKeysAreIgnored(): void
    {
        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => 1,
            'not_a_property' => 'whatever',
        ]);

        $this->assertSame(1, $dto->id);
        $this->assertFalse($dto->has('not_a_property'));
    }

    public function testAlreadyCorrectTypesPassThrough(): void
    {
        $date = new DateTimeImmutable('2026-01-15 09:00:00');

        $dto = $this->hydrator->hydrate(ProfileDto::class, [
            'id' => 7,
            'created_at' => $date,
            'address' => new AddressDto(['city' => 'Oslo']),
        ]);

        $this->assertSame(7, $dto->id);
        $this->assertSame($date, $dto->created_at);
        $this->assertInstanceOf(AddressDto::class, $dto->address);
        $this->assertSame('Oslo', $dto->address->city);
    }

    public function testBoolCoercionFromCommonRepresentations(): void
    {
        $this->assertFalse($this->hydrator->hydrate(ProfileDto::class, ['active' => '0'])->active);
        $this->assertTrue($this->hydrator->hydrate(ProfileDto::class, ['active' => 'true'])->active);
        $this->assertFalse($this->hydrator->hydrate(ProfileDto::class, ['active' => 0])->active);
    }

    public function testUncoercibleScalarThrowsHydrationExceptionWithFieldContext(): void
    {
        try {
            $this->hydrator->hydrate(ProfileDto::class, ['id' => 'not-a-number']);
            $this->fail('Expected HydrationException');
        } catch (HydrationException $hydrationException) {
            $this->assertInstanceOf(PersistenceExceptionInterface::class, $hydrationException);
            $this->assertStringContainsString('id', $hydrationException->getMessage());
        }
    }

    public function testUncoercibleNestedDataObjectThrows(): void
    {
        $this->expectException(HydrationException::class);

        // address expects an array (or AddressDto), not a scalar.
        $this->hydrator->hydrate(ProfileDto::class, ['address' => 'not-an-array']);
    }

    public function testHydrateManyProjectsEveryRow(): void
    {
        $rows = [
            ['id' => '1', 'name' => 'Vega'],
            ['id' => '2', 'name' => 'Rigel'],
        ];

        $dtos = $this->hydrator->hydrateMany(ProfileDto::class, $rows);

        $this->assertCount(2, $dtos);
        $this->assertContainsOnlyInstancesOf(ProfileDto::class, $dtos);
        $this->assertSame(1, $dtos[0]->id);
        $this->assertSame('Rigel', $dtos[1]->name);
    }

    public function testHydrateManyReturnsEmptyListForNoRows(): void
    {
        $this->assertSame([], $this->hydrator->hydrateMany(ProfileDto::class, []));
    }
}
