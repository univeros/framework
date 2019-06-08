<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\CacheLimiterInterface;
use Psr\Http\Message\ResponseInterface;

class PrivateCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritDoc
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        return (new PrivateNoExpireCacheLimiter($this->cacheExpire))
            ->apply($response->withAddedHeader('Expires', CacheLimiterInterface::EXPIRED));
    }
}
