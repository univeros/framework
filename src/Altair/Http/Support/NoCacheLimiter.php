<?php
namespace Altair\Http\Support;

use Altair\Http\Contracts\CacheLimiterInterface;
use Psr\Http\Message\ResponseInterface;

class NoCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritdoc
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        return $response
            ->withAddedHeader('Expires', CacheLimiterInterface::EXPIRED)
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0')
            ->withAddedHeader('Pragma', 'no-cache');
    }
}
