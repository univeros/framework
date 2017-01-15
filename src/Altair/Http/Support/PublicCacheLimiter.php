<?php
namespace Altair\Http\Support;

use Psr\Http\Message\ResponseInterface;

class PublicCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritdoc
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        $maxAge = $this->cacheExpire * 60;
        $expires = $this->timestamp($maxAge);
        $cacheControl = sprintf('public, max-age=%s', $maxAge);
        $lastModified = $this->timestamp();

        return $response
            ->withAddedHeader('Expires', $expires)
            ->withAddedHeader('Cache-Control', $cacheControl)
            ->withAddedHeader('Last-Modified', $lastModified);
    }
}
