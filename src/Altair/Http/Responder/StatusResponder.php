<?php declare(strict_types=1);

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class StatusResponder implements ResponderInterface
{
    /**
     * @var HttpStatusCollection
     */
    protected $httpStatusCollection;

    /**
     * StatusResponder constructor.
     *
     * @param HttpStatusCollection $httpStatusCollection
     */
    public function __construct(HttpStatusCollection $httpStatusCollection)
    {
        $this->httpStatusCollection = $httpStatusCollection;
    }

    /**
     * @inheritDoc
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ): ResponseInterface {
        if ($this->hasStatus($payload)) {
            $response = $this->status($response, $payload);
        }

        return $response;
    }

    /**
     * Determine if the payload has a status.
     *
     * @param PayloadInterface $payload
     *
     * @return boolean
     */
    private function hasStatus(PayloadInterface $payload): bool
    {
        return (bool)$payload->getStatus();
    }

    /**
     * Get the response with the status code from the payload.
     *
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @throws InvalidArgumentException If the requested $statusText is not valid
     * @throws OutOfBoundsException     If not status code is found
     * @return ResponseInterface
     *
     */
    private function status(ResponseInterface $response, PayloadInterface $payload): ResponseInterface
    {
        $status = $payload->getStatus();
        $code = $this->httpStatusCollection->getStatusCode($status);

        try {
            return $response->withStatus($code);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }
}
