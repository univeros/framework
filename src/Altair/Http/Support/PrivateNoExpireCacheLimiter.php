<?php
namespace Altair\Http\Support;

use Psr\Http\Message\ResponseInterface;

class PrivateNoExpireCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritdoc
     */
    public function apply(ResponseInterface $response): ResponseInterface
    {
        $maxAge = $this->cacheExpire * 60;
        $cacheControl = sprintf('private, max-age=%1$s, pre-check=%1$s', $maxAge);
        $lastModified = $this->timestamp();

        return $response
            ->withAddedHeader('Cache-Control', $cacheControl)
            ->withAddedHeader('Last-Modified', $lastModified);
    }
}
