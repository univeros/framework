<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Contracts;

use Psr\Http\Server\MiddlewareInterface;

/**
 * A module that contributes PSR-15 middleware to the host's HTTP pipeline.
 *
 * Unlike routes, middleware ordering is load-bearing (the exception handler
 * must wrap everything, action-aware guards must run after routing but before
 * the action). Each entry therefore carries an integer `priority`: lower runs
 * earlier / more outer. The front controller merges these into the Relay queue
 * and sorts by priority before dispatch.
 *
 * Position relative to the framework's own stages with the documented anchors
 * in {@see \Altair\Http\Support\MiddlewarePriority} — e.g. an action-aware
 * guard uses `MiddlewarePriority::DISPATCHER + 10` (after routing, before the
 * action). Keep ordinary guards strictly between the anchors
 * (`EXCEPTION_HANDLER < priority < ACTION`): the action stage is terminal, so a
 * priority `>= ACTION` never runs on a matched route. Class-string entries are
 * resolved through the container, so the middleware's own dependencies are
 * autowired.
 */
interface MiddlewareProviderInterface
{
    /**
     * @return list<array{middleware: class-string<MiddlewareInterface>|MiddlewareInterface, priority: int}>
     */
    public function middleware(): array;
}
