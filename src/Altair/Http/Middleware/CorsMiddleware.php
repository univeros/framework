<?php declare(strict_types=1);

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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var AnalyzerInterface
     */
    protected $analyzer;

    /**
     * CorsMiddleware constructor.
     *
     * @param AnalyzerInterface $analyzer
     */
    public function __construct(AnalyzerInterface $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    /**
     * @inheritDoc
     *
     * @throws \InvalidArgumentException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $cors = $this->analyzer->analyze($request);

        switch ($cors->getRequestType()) {
            case AnalysisResultInterface::ERR_NO_HOST_HEADER:
            case AnalysisResultInterface::ERR_ORIGIN_NOT_ALLOWED:
            case AnalysisResultInterface::ERR_METHOD_NOT_SUPPORTED:
            case AnalysisResultInterface::ERR_HEADERS_NOT_SUPPORTED:
                return $response->withStatus(HttpStatusCodeInterface::HTTP_FORBIDDEN);
            case AnalysisResultInterface::TYPE_REQUEST_OUT_OF_CORS_SCOPE:
                return $next($request, $response);
            case AnalysisResultInterface::TYPE_PRE_FLIGHT_REQUEST:
                $response = $this->applyCorsResponseHeaders($cors, $response);

                return $response->withStatus(HttpStatusCodeInterface::HTTP_OK);
            default:
                $response = $next($request, $response);

                return $this->applyCorsResponseHeaders($cors, $response);
        }
    }

    /**
     * Adds cors response headers to the response
     *
     * @param AnalysisResultInterface $cors
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function applyCorsResponseHeaders(
        AnalysisResultInterface $cors,
        ResponseInterface $response
    ): ResponseInterface {
        foreach ($cors->getResponseHeaders() as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
