<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class IpAddressMiddleware implements MiddlewareInterface
{
    /**
     * @var array
     */
    protected $headers = [
        'Forwarded',
        'Forwarded-For',
        'Client-Ip',
        'X-Forwarded',
        'X-Forwarded-For',
        'X-Cluster-Client-Ip',
    ];

    /**
     * IpAddressMiddleware constructor.
     * @param array|null $headers
     */
    public function __construct(array $headers = null)
    {
        $this->headers = $headers?? $this->headers;
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param callable $next
     * @return mixed
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS, $this->scanIps($request));

        return $next($request, $response);
    }

    /**
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function scanIps(ServerRequestInterface $request): array
    {
        $server = $request->getServerParams();
        $ips = [];

        if (!empty($server['REMOTE_ADDR']) && filter_var($server['REMOTE_ADDR'], FILTER_VALIDATE_IP)) {
            $ips[] = $server['REMOTE_ADDR'];
        }
        foreach ($this->headers as $name) {
            $header = $request->getHeaderLine($name);
            if (!empty($header)) {
                foreach (array_map('trim', explode(',', $header)) as $ip) {
                    if ((array_search($ip, $ips) === false) && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $ips[] = $ip;
                    }
                }
            }
        }
        return $ips;
    }
}
