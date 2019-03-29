<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Contracts;

interface UpdateRepositoryInterface
{
    /**
     * Update an object on its storage and return the updated version.
     *
     * @param integer $id
     * @param array $values
     *
     * @return object
     */
    public function update($id, array $values);
}
