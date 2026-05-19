<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\ErrorHandlerInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\StatusCodeValidatorInterface;
use Altair\Http\Support\DefaultErrorHandler;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    private readonly ErrorHandlerInterface $handler;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        ?ErrorHandlerInterface $handler = null,
        private readonly ?StatusCodeValidatorInterface $validator = null,
        private readonly bool $capture = false,
    ) {
        $this->handler = $handler ?? new DefaultErrorHandler();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ob_start();
        $level = ob_get_level();

        try {
            $response = $handler->handle($request);

            return $this->isError($response->getStatusCode())
                ? $this->handleError($request, null, $response->getStatusCode())
                : $response;
        } catch (Throwable $e) {
            if (!$this->capture) {
                throw $e;
            }

            return $this->handleError($request, $e);
        } finally {
            while (ob_get_level() >= $level) {
                ob_end_clean();
            }
        }
    }

    private function handleError(
        ServerRequestInterface $request,
        ?Throwable $exception,
        int $code = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR,
    ): ResponseInterface {
        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION, $exception);

        return ($this->handler)($request, $this->responseFactory->createResponse($code));
    }

    private function isError(int $code): bool
    {
        return $this->validator !== null
            ? ($this->validator)($code)
            : ($code >= HttpStatusCodeInterface::HTTP_BAD_REQUEST && $code < HttpStatusCodeInterface::HTTP_MAX_RANGE);
    }
}
