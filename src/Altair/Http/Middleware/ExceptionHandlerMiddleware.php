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
use Altair\Http\Contracts\HttpExceptionInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\StatusCodeValidatorInterface;
use Altair\Http\Support\DefaultErrorHandler;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class ExceptionHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly ErrorHandlerInterface $handler = new DefaultErrorHandler(),
        private readonly ?StatusCodeValidatorInterface $validator = null,
        private readonly bool $capture = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ob_start();
        $level = ob_get_level();

        try {
            $response = $handler->handle($request);

            return $this->isError($response->getStatusCode())
                ? $this->handleError($request, null, $response->getStatusCode())
                : $response;
        } catch (Throwable $throwable) {
            if (!$this->capture) {
                throw $throwable;
            }

            $status = $this->statusFor($throwable);
            $this->logServerError($throwable, $request, $status);

            return $this->handleError($request, $throwable, $status, $this->headersFor($throwable));
        } finally {
            while (ob_get_level() >= $level) {
                ob_end_clean();
            }
        }
    }

    /**
     * @param array<string, string> $headers
     */
    private function handleError(
        ServerRequestInterface $request,
        ?Throwable $exception,
        int $code = HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR,
        array $headers = [],
    ): ResponseInterface {
        $request = $request->withAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION, $exception);

        $response = $this->responseFactory->createResponse($code);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return ($this->handler)($request, $response);
    }

    /**
     * The status a thrown exception maps to: its own when it declares one,
     * otherwise a generic 500. This is what makes a thrown 404/405/422 render
     * with the correct status instead of collapsing to 500.
     */
    private function statusFor(Throwable $throwable): int
    {
        return $throwable instanceof HttpExceptionInterface
            ? $throwable->getStatusCode()
            : HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR;
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(Throwable $throwable): array
    {
        return $throwable instanceof HttpExceptionInterface
            ? $throwable->getHeaders()
            : [];
    }

    /**
     * Server-side failures (5xx) are the ones worth remembering. Client errors
     * (404/422/...) are expected and would only add noise, so they are skipped.
     */
    private function logServerError(Throwable $throwable, ServerRequestInterface $request, int $status): void
    {
        if (!$this->logger instanceof LoggerInterface
            || $status < HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR) {
            return;
        }

        $this->logger->error($throwable->getMessage(), [
            'exception' => $throwable,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $status,
        ]);
    }

    private function isError(int $code): bool
    {
        return $this->validator instanceof StatusCodeValidatorInterface
            ? ($this->validator)($code)
            : ($code >= HttpStatusCodeInterface::HTTP_BAD_REQUEST && $code < HttpStatusCodeInterface::HTTP_MAX_RANGE);
    }
}
