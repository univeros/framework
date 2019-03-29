<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface TokenExtractorInterface
{
    /**
     * Returns the authorization token from the request (if any).
     *
     * @param ServerRequestInterface $request
     *
     * @return null|string
     */
    public function extract(ServerRequestInterface $request): ?string;
}
