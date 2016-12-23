<?php
namespace Altair\Http\Responder;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RedirectResponder implements ResponderInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload
    ) {
        $location = $payload->getSetting('redirect');

        if (!empty($location)) {
            $response = $response->withHeader('Location', $location);
        }

        return $response;
    }
}
