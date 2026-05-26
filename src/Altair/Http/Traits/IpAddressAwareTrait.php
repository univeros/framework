<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Traits;

use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

trait IpAddressAwareTrait
{
    protected function getIps(ServerRequestInterface $request):? array
    {
        return $request->getAttribute(MiddlewareInterface::ATTRIBUTE_IP_ADDRESS);
    }

    protected function getIp(ServerRequestInterface $request):? string
    {
        $ips = $this->getIps($request);

        return $ips[0]?? null;
    }
}
