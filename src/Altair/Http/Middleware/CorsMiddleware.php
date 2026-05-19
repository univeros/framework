<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Neomerx\Cors\Contracts\AnalysisResultInterface;
use Neomerx\Cors\Contracts\AnalyzerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AnalyzerInterface $analyzer,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cors = $this->analyzer->analyze($request);

        return match ($cors->getRequestType()) {
            AnalysisResultInterface::ERR_NO_HOST_HEADER,
            AnalysisResultInterface::ERR_ORIGIN_NOT_ALLOWED,
            AnalysisResultInterface::ERR_METHOD_NOT_SUPPORTED,
            AnalysisResultInterface::ERR_HEADERS_NOT_SUPPORTED =>
                $this->responseFactory->createResponse(HttpStatusCodeInterface::HTTP_FORBIDDEN),
            AnalysisResultInterface::TYPE_REQUEST_OUT_OF_CORS_SCOPE =>
                $handler->handle($request),
            AnalysisResultInterface::TYPE_PRE_FLIGHT_REQUEST =>
                $this->applyCorsResponseHeaders(
                    $cors,
                    $this->responseFactory->createResponse(HttpStatusCodeInterface::HTTP_OK),
                ),
            default =>
                $this->applyCorsResponseHeaders($cors, $handler->handle($request)),
        };
    }

    private function applyCorsResponseHeaders(
        AnalysisResultInterface $cors,
        ResponseInterface $response,
    ): ResponseInterface {
        foreach ($cors->getResponseHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
