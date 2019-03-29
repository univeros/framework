<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Contracts;

interface CreateRepositoryInterface
{
    /**
     * Create a new object and return it
     *
     * @param array $values
     *
     * @return EntityInterface
     */
    public function create(array $values): EntityInterface;
}
