<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Middleware\Contracts;

interface MiddlewareInterface
{
    /**
     * Middleware capable invokable class method.
     *
     * @param PayloadInterface $payload
     * @param callable $next
     *
     * @return mixed
     */
    public function __invoke(PayloadInterface $payload, callable $next);
}
