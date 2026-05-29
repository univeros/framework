<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware\RateLimit;

use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Middleware\RateLimit\Contracts\KeyResolverInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The default key resolver: client IP.
 *
 * Reads {@see MiddlewareInterface::ATTRIBUTE_IP_ADDRESS} first so a host that
 * has already resolved the real client through `IpAddressMiddleware` (with
 * its configured trusted-proxy list) gets the right key; falls back to
 * `REMOTE_ADDR` for unproxied / dev-server runs. When no IP can be resolved
 * the key is `'unknown'` — the limiter still works, it just buckets all
 * unknowns together, which is the conservative choice.
 *
 * **Proxy caveat.** Do NOT blindly read `X-Forwarded-For` here: a public-
 * facing host with no proxy-trust enforcement lets any client spoof the
 * header, defeating the limiter. Resolve the trusted IP upstream
 * (`IpAddressMiddleware`) and let this resolver consume the attribute.
 */
final readonly class IpKeyResolver implements KeyResolverInterface
{
    #[Override]
    public function resolve(ServerRequestInterface $request): string
    {
        $attribute = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS);
        if (\is_string($attribute) && $attribute !== '') {
            return $attribute;
        }

        $params = $request->getServerParams();
        $remote = $params['REMOTE_ADDR'] ?? null;

        return \is_string($remote) && $remote !== '' ? $remote : 'unknown';
    }
}
