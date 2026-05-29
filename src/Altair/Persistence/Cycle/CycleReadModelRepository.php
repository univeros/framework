<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cycle;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Persistence\Contracts\HydratorInterface;
use Altair\Persistence\Contracts\ReadModelRepositoryInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Override;

/**
 * Cycle-backed read model: selects raw rows for an entity role and projects
 * each one into an immutable Data object through the hydrator.
 *
 * Reads bypass the identity map — {@see Select::fetchData()} returns plain
 * arrays rather than managed entities — which is what you want for the read
 * side. Coercion and nested-object composition are handled by the hydrator.
 *
 * @template TDataObject of DataObjectInterface
 *
 * @implements ReadModelRepositoryInterface<TDataObject>
 */
final readonly class CycleReadModelRepository implements ReadModelRepositoryInterface
{
    /**
     * @param class-string             $entityClass     the Cycle entity role to read from
     * @param class-string<TDataObject> $dataObjectClass the Data object to project rows into
     */
    public function __construct(
        private string $entityClass,
        private string $dataObjectClass,
        private ORMInterface $orm,
        private HydratorInterface $hydrator,
    ) {}

    #[Override]
    public function find(int|string $id): ?DataObjectInterface
    {
        $row = $this->firstRow($this->select()->wherePK($id));

        return $row === null ? null : $this->hydrator->hydrate($this->dataObjectClass, $row);
    }

    #[Override]
    public function findOneBy(array $criteria): ?DataObjectInterface
    {
        $row = $this->firstRow($this->select()->where($criteria));

        return $row === null ? null : $this->hydrator->hydrate($this->dataObjectClass, $row);
    }

    #[Override]
    public function findBy(array $criteria): array
    {
        /** @var iterable<array<string, mixed>> $rows */
        $rows = $this->select()->where($criteria)->fetchData();

        return $this->hydrator->hydrateMany($this->dataObjectClass, $rows);
    }

    #[Override]
    public function findAll(): array
    {
        /** @var iterable<array<string, mixed>> $rows */
        $rows = $this->select()->fetchData();

        return $this->hydrator->hydrateMany($this->dataObjectClass, $rows);
    }

    /**
     * @return Select<object>
     */
    private function select(): Select
    {
        return new Select($this->orm, $this->entityClass);
    }

    /**
     * @param Select<object> $select
     *
     * @return array<string, mixed>|null
     */
    private function firstRow(Select $select): ?array
    {
        foreach ($select->fetchData() as $row) {
            /** @var array<string, mixed> $row */
            return $row;
        }

        return null;
    }
}
