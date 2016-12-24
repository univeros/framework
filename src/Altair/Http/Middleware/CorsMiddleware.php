<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\AnalysisResultInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var Analyzer
     */
    protected $analyzer;

    /**
     * CorsMiddleware constructor.
     * @param Analyzer $analyzer
     */
    public function __construct(Analyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }

    /**
     * @inheritdoc
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
     * @param AnalysisResultInterface $cors
     * @param ResponseInterface $response
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
