<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware\RateLimit\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Strategy for picking the counter key from a request.
 *
 * The default {@see \Altair\Http\Middleware\RateLimit\IpKeyResolver} keys on
 * the client IP; an API-key/user-id resolver is one line:
 * `static fn($r) => 'user:' . $r->getAttribute('user_id', 'anonymous')`.
 *
 * Hosts behind a reverse proxy should set the trusted IP on the request via
 * `IpAddressMiddleware` BEFORE this middleware runs — `IpKeyResolver` reads
 * that attribute first so the proxy-trust decision lives in one place.
 */
interface KeyResolverInterface
{
    public function resolve(ServerRequestInterface $request): string;
}
