<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Override;

class HttpBadRequestException extends HttpException
{
    /**
     * A bad-request and its subclasses are client errors: fall back to 400
     * (not the base class's 500) when no explicit code was supplied.
     */
    #[Override]
    protected function defaultStatusCode(): int
    {
        return HttpStatusCodeInterface::HTTP_BAD_REQUEST;
    }
}
