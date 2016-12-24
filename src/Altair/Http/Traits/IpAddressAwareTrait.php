<?php
namespace Altair\Http\Traits;


use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

trait IpAddressAwareTrait
{
    /**
     * @param ServerRequestInterface $request
     * @return array|null
     */
    protected function getIps(ServerRequestInterface $request):? array
    {
        return $request->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS);
    }

    /**
     * @param ServerRequestInterface $request
     * @return null|string
     */
    protected function getIp(ServerRequestInterface $request):? string
    {
        $ips = $this->getIps($request);

        return $ips[0]?? null;
    }
}