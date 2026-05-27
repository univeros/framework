<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Contracts;

/**
 * Identity-map / change-tracker contract.
 *
 * The unit of work batches persist/remove operations so they can be
 * flushed inside a single transaction. Concrete implementations are
 * expected to maintain their own identity map.
 */
interface UnitOfWorkInterface
{
    /**
     * Schedule the entity for insertion or update on the next flush.
     */
    public function persist(object $entity): void;

    /**
     * Schedule the entity for removal on the next flush.
     */
    public function remove(object $entity): void;

    /**
     * Commit every pending change inside one transaction.
     */
    public function flush(): void;

    /**
     * Discard every pending change and detach all tracked entities.
     */
    public function clear(): void;
}
