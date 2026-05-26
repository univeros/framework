<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Responder;

use Altair\Http\Collection\HttpStatusCollection;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\OutOfBoundsException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StatusResponder implements ResponderInterface
{
    /**
     * StatusResponder constructor.
     */
    public function __construct(protected HttpStatusCollection $httpStatusCollection) {}

    /**
     * @inheritDoc
     */
    #[Override]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        if ($this->hasStatus($payload)) {
            return $this->status($response, $payload);
        }

        return $response;
    }

    /**
     * Determine if the payload has a status.
     *
     *
     */
    private function hasStatus(PayloadInterface $payload): bool
    {
        return (bool) $payload->getStatus();
    }

    /**
     * Get the response with the status code from the payload.
     *
     *
     * @throws InvalidArgumentException If the requested $statusText is not valid
     * @throws OutOfBoundsException     If not status code is found
     *
     */
    private function status(ResponseInterface $response, PayloadInterface $payload): ResponseInterface
    {
        $status = $payload->getStatus();
        $code = $this->httpStatusCollection->getStatusCode($status);

        try {
            return $response->withStatus($code);
        } catch (\InvalidArgumentException $invalidArgumentException) {
            throw new InvalidArgumentException($invalidArgumentException->getMessage(), $invalidArgumentException->getCode(), $invalidArgumentException->getPrevious());
        }
    }
}
