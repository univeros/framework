<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Psr\Http\Message\ResponseInterface;

class PrivateNoExpireCacheLimiter extends AbstractCacheLimiter
{
    /**
     * @inheritDoc
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
