<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

interface TokenInterface
{
    const TOKEN_KEY = 'altair:http:token';

    /**
     * @return string
     */
    public function getToken(): string;

    /**
     * Returns a value from its metadata if any.
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getMetadata(string $key = null);
}
