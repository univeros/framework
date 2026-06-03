<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Altair\Http\Contracts\HttpExceptionInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Exception;
use Override;

class HttpException extends Exception implements HttpExceptionInterface
{
    /**
     * The status this exception maps to: the carried code when it is a valid
     * 4xx/5xx value, otherwise {@see self::defaultStatusCode()} (500 for the
     * base class; subclasses narrow it — e.g. bad-request to 400).
     */
    #[Override]
    public function getStatusCode(): int
    {
        $code = $this->getCode();

        return \is_int($code)
            && $code >= HttpStatusCodeInterface::HTTP_BAD_REQUEST
            && $code <= HttpStatusCodeInterface::HTTP_MAX_RANGE
                ? $code
                : $this->defaultStatusCode();
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getHeaders(): array
    {
        return [];
    }

    /**
     * Fallback status when no valid HTTP code was supplied to the constructor.
     */
    protected function defaultStatusCode(): int
    {
        return HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
    }
}
