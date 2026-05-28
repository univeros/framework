<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Session\Contracts;

interface CsrfTokenInterface
{
    /**
     * Checks whether an incoming CSRF token value is valid.
     *
     *
     */
    public function isValid(string $value): bool;

    /**
     * Returns the value of the outgoing CSRF token.
     */
    public function getValue(): string;

    /**
     * Regenerates the value of the outgoing CSRF token.
     */
    public function generateValue(): string;
}
