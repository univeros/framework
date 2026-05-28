<?php

declare(strict_types=1);

namespace App\Http\Responders;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Marshals the ping payload into a JSON response.
 */
final class PingResponder implements ResponderInterface
{
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload,
    ): ResponseInterface {
        return new JsonResponse($payload->getOutput(), $payload->getStatus() ?? 200);
    }

    /**
     * @return list<int>
     */
    public static function statuses(): array
    {
        return [200];
    }
}
