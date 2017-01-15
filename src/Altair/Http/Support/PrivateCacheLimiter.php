<?php
namespace Altair\Http\Support;

use Altair\Http\Contracts\CacheLimiterInterface;
use Psr\Http\Message\ResponseInterface;

class PrivateCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritdoc
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        return (new PrivateNoExpireCacheLimiter($this->cacheExpire))
            ->apply($response->withAddedHeader('Expires', CacheLimiterInterface::EXPIRED));
    }
}
