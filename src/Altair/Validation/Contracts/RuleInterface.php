<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Contracts;

use Altair\Middleware\Contracts\MiddlewareInterface;

interface RuleInterface extends MiddlewareInterface
{
    /**
     * Checks whether a value passes rule specs validation.
     *
     *
     */
    public function assert(mixed $value): bool;
}
