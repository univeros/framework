<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Contracts;

interface DeleteRepositoryInterface
{
    /**
     * Delete a single object by identifier
     *
     * @param mixed $id
     *
     * @return boolean
     */
    public function delete($id): bool;

    /**
     * Deletes all objects in db.
     *
     * @return bool
     */
    public function deleteAll(): bool;

    /**
     * Delete a single object by variable criteria
     *
     * @param array $criteria
     *
     * @return boolean
     */
    public function deleteOneBy(array $criteria): bool;

    /**
     * Delete multiple objects by variable criteria
     *
     * @param array $criteria
     *
     * @return integer the number of objects successfully deleted.
     */
    public function deleteAllBy(array $criteria);
}
