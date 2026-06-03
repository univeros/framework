<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Exception;

use Override;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class HttpMethodNotAllowedException extends HttpBadRequestException
{
    /**
     * @param list<string> $allowed
     */
    public function __construct(
        protected array $allowed = [],
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    public function getHeaders(): array
    {
        return $this->allowed === []
            ? []
            : ['Allow' => implode(',', $this->allowed)];
    }

    public function withResponse(ResponseInterface $response): ResponseInterface
    {
        return $this->allowed === []
            ? $response
            : $response->withHeader('Allow', implode(',', $this->allowed));
    }
}
