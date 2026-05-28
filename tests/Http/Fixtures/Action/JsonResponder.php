<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Contracts\ResponderInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Mirrors the scaffolded responder: marshal the payload output into a JSON
 * response using the payload status.
 */
final class JsonResponder implements ResponderInterface
{
    #[Override]
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
        PayloadInterface $payload,
    ): ResponseInterface {
        return new JsonResponse($payload->getOutput(), $payload->getStatus() ?? 200);
    }
}
