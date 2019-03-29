<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface CacheLimiterInterface
{
    const EXPIRED = 'Thu, 19 Nov 1981 08:52:00 GMT';

    /**
     * Implements cache limiter to the response message
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function apply(ResponseInterface $response): ResponseInterface;
}
