<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\FormatNegotiatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class FormatNegotiatorMiddleware implements MiddlewareInterface
{
    /**
     * @var FormatNegotiatorInterface
     */
    protected $negotiator;

    /**
     * FormatNegotiatorMiddleware constructor.
     *
     * @param FormatNegotiatorInterface $negotiator
     */
    public function __construct(FormatNegotiatorInterface $negotiator)
    {
        $this->negotiator = $negotiator;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $format = $this->negotiator->getFromServerRequestUriPath(
            $request
        ) ?: $this->negotiator->getFromServerRequestHeaderLine($request) ?: FormatNegotiatorInterface::DEFAULT_FORMAT;
        $contentType = $this->negotiator->getContentTypeByFormat($format);

        /** @var ResponseInterface $response */
        $response = $next(
            $request->withAttribute(MiddlewareInterface::ATTRIBUTE_FORMAT, $format),
            $response->withHeader('Content-Type', $contentType)
        );

        return !$response->hasHeader('Content-Type')
            ? $response->withHeader('Content-Type', $contentType)
            : $response;
    }
}
