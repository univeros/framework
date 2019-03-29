<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface StatusCodeValidatorInterface
{
    /**
     * Checks whether the response status code is valid or not.
     *
     * @param int $code
     *
     * @return bool
     */
    public function __invoke(int $code): bool;
}
