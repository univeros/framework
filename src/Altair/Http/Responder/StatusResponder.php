<?php
namespace Altair\Http\Responder;

use Altair\Http\Collection\HttpStatusCollection;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
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
    ) {
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
    private function hasStatus(PayloadInterface $payload)
    {
        return (bool) $payload->getStatus();
    }
    /**
     * Get the response with the status code from the payload.
     *
     * @param ResponseInterface $response
     * @param PayloadInterface $payload
     *
     * @return ResponseInterface
     */
    private function status(
        ResponseInterface $response,
        PayloadInterface $payload
    ) {
        $status = $payload->getStatus();
        $code = $this->httpStatusCollection->getStatusCode($status);

        return $response->withStatus($code);
    }
}
