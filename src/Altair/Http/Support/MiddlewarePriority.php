<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

/**
 * Documented priority anchors for the framework's own pipeline stages.
 *
 * Module middleware (via {@see \Altair\Module\Contracts\MiddlewareProviderInterface})
 * positions itself relative to these. Lower priority runs earlier / more outer;
 * {@see ModuleMiddleware::collect()} stable-sorts the merged queue by priority.
 *
 * The three anchors map to the front controller's built-in stages:
 *
 * ```
 *   EXCEPTION_HANDLER (0) ── outermost; turns any throwable into a response
 *        │  ┌──────────────── band: pre-routing guards (CORS, rate-limit, …)
 *        │  │                  use a priority < DISPATCHER
 *   DISPATCHER (500) ──────── matches the route, sets the action attribute
 *        │  │  ┌───────────── band: action-aware guards (auth, idempotency)
 *        │  │  │              use DISPATCHER < priority < ACTION
 *   ACTION (1000) ─────────── innermost; resolves and runs the matched action
 * ```
 *
 * The wide gap between anchors (500) leaves room for several module middleware
 * to slot into a band while staying deterministically ordered; pick offsets
 * like `DISPATCHER + 10`, `DISPATCHER + 20` to order modules within a band.
 *
 * Bounds. `ACTION` is the innermost stage and is *terminal* on a matched route
 * — it produces the response without delegating further, so middleware at a
 * priority `>= ACTION` never runs once a route matches. A priority `<
 * EXCEPTION_HANDLER` is permitted but runs OUTSIDE the exception handler, so its
 * own throwables are not turned into problem+json responses — reserve it for a
 * deliberate outermost wrapper (top-level timing/logging). For ordinary guards,
 * keep `EXCEPTION_HANDLER < priority < ACTION`.
 */
final class MiddlewarePriority
{
    /** Outermost stage: converts any thrown exception into an HTTP response. */
    public const int EXCEPTION_HANDLER = 0;

    /** Routing stage: matches the request to an action and records it on the request. */
    public const int DISPATCHER = 500;

    /** Innermost stage: resolves and executes the matched action. */
    public const int ACTION = 1000;

    private function __construct() {}
}
