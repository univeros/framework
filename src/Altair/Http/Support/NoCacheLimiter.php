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
