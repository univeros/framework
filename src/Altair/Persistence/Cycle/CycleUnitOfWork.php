<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Cycle;

use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Cycle\ORM\EntityManager;
use Cycle\ORM\EntityManagerInterface as CycleEntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Override;

/**
 * Cycle-backed implementation of {@see UnitOfWorkInterface}.
 *
 * Wraps Cycle's transactional EntityManager and resets it after every
 * flush so the same instance can be reused across requests.
 */
final class CycleUnitOfWork implements UnitOfWorkInterface
{
    private CycleEntityManagerInterface $manager;

    public function __construct(private readonly ORMInterface $orm)
    {
        $this->manager = new EntityManager($this->orm);
    }

    #[Override]
    public function persist(object $entity): void
    {
        $this->manager->persist($entity);
    }

    #[Override]
    public function remove(object $entity): void
    {
        $this->manager->delete($entity);
    }

    #[Override]
    public function flush(): void
    {
        $this->manager->run();
        $this->manager = new EntityManager($this->orm);
    }

    #[Override]
    public function clear(): void
    {
        $this->orm->getHeap()->clean();
        $this->manager = new EntityManager($this->orm);
    }
}
