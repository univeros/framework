<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class IpAddressMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private const array DEFAULT_HEADERS = [
        'Forwarded',
        'Forwarded-For',
        'Client-Ip',
        'X-Forwarded',
        'X-Forwarded-For',
        'X-Cluster-Client-Ip',
    ];

    /**
     * @param list<string> $headers
     */
    public function __construct(
        private readonly array $headers = self::DEFAULT_HEADERS,
    ) {
    }

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $request = $request->withAttribute(
            MiddlewareInterface::ATTRIBUTE_IP_ADDRESS,
            $this->scanIps($request),
        );

        return $handler->handle($request);
    }

    /**
     * @return list<string>
     */
    private function scanIps(ServerRequestInterface $request): array
    {
        $server = $request->getServerParams();
        $ips = [];

        if (!empty($server['REMOTE_ADDR']) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $server['REMOTE_ADDR'];
        }

        foreach ($this->headers as $name) {
            $header = $request->getHeaderLine($name);
            if ($header === '') {
                continue;
            }

            foreach (array_map('trim', explode(',', $header)) as $ip) {
                if (!in_array($ip, $ips, true) && filter_var($ip, FILTER_VALIDATE_IP)) {
                    $ips[] = $ip;
                }
            }
        }

        return $ips;
    }
}
