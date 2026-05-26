<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Support\CidrMatcher;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Allows / denies requests based on the client IP address. Reads the IP list
 * set on the request by {@see IpAddressMiddleware} (the
 * `MiddlewareInterface::ATTRIBUTE_IP_ADDRESS` attribute) and matches it
 * against configurable CIDR ranges.
 *
 * Resolution order:
 *  1. If the address matches any deny pattern → 403 (deny wins).
 *  2. Else if an allow list is configured and the address matches none of
 *     those patterns → 403.
 *  3. Else → request passes through.
 *
 * An empty allow list means "anyone not denied is allowed"; an allow list
 * with entries acts as a whitelist.
 *
 * Must run AFTER {@see IpAddressMiddleware} in the pipeline.
 */
class IpRestrictionMiddleware implements MiddlewareInterface
{
    private readonly CidrMatcher $allow;
    private readonly CidrMatcher $deny;
    private readonly bool $allowConfigured;

    /**
     * @param list<string> $allow CIDR ranges or exact IPs to allow. Empty = no allowlist gate.
     * @param list<string> $deny  CIDR ranges or exact IPs to deny. Takes precedence over allow.
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        array $allow = [],
        array $deny = [],
        private readonly int $deniedStatusCode = HttpStatusCodeInterface::HTTP_FORBIDDEN,
    ) {
        $this->allow = new CidrMatcher($allow);
        $this->deny = new CidrMatcher($deny);
        $this->allowConfigured = $allow !== [];
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ips = $this->extractIps($request);

        if ($ips === []) {
            // No IP information available — fail closed only if an allowlist is configured,
            // otherwise let the request through (upstream IpAddressMiddleware may be absent
            // in tests or non-public deployments).
            return $this->allowConfigured
                ? $this->responseFactory->createResponse($this->deniedStatusCode)
                : $handler->handle($request);
        }

        foreach ($ips as $ip) {
            if ($this->deny->matches($ip)) {
                return $this->responseFactory->createResponse($this->deniedStatusCode);
            }
        }

        if (!$this->allowConfigured) {
            return $handler->handle($request);
        }

        foreach ($ips as $ip) {
            if ($this->allow->matches($ip)) {
                return $handler->handle($request);
            }
        }

        return $this->responseFactory->createResponse($this->deniedStatusCode);
    }

    /**
     * @return list<string>
     */
    private function extractIps(ServerRequestInterface $request): array
    {
        $ips = $request->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS);
        if (\is_array($ips)) {
            return array_values(array_filter($ips, 'is_string'));
        }
        if (\is_string($ips) && $ips !== '') {
            return [$ips];
        }

        return [];
    }
}
