<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Cookie\Contracts;

interface CookieInterface
{
    /**
     * The name of the cookie header
     */
    const HEADER = 'Cookie';

    /**
     * Returns the name of the cookie
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Returns the value of the cookie
     *
     * @return null|string
     */
    public function getValue(): ?string;
}
