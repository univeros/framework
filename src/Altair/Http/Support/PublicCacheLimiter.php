<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
