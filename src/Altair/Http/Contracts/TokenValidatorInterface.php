<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface TokenValidatorInterface
{
    /**
     * Validates whether the given token string is valid or not. Should work in conjunction with TokenBuilderInterface.
     *
     * @param string $token
     *
     * @return bool
     */
    public function validate(string $token): bool;
}
