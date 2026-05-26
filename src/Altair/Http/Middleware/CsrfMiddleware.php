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
use Altair\Http\Support\MimeType;
use Altair\Http\Traits\IpAddressAwareTrait;
use Altair\Session\SessionManager;
use Laminas\Diactoros\Stream;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CsrfMiddleware implements MiddlewareInterface
{
    use IpAddressAwareTrait;

    private const string PARAM = '_csrf';

    public function __construct(
        private readonly SessionManager $sessionManager,
        private readonly MimeType $mimeType,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isUnsafeMethod($request) && !$this->validateCsrfTokenFromRequest($request)) {
            return $this->responseFactory->createResponse(HttpStatusCodeInterface::HTTP_FORBIDDEN);
        }

        $response = $handler->handle($request);

        return $this->mimeType->getFromResponseHeaderLine($response) === 'text/html'
            ? $this->insertCsrfIntoPostForms($response)
            : $response;
    }

    private function isUnsafeMethod(ServerRequestInterface $request): bool
    {
        return !\in_array(
            strtoupper($request->getMethod()),
            ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS'],
            true,
        );
    }

    private function validateCsrfTokenFromRequest(ServerRequestInterface $request): bool
    {
        $data = $request->getParsedBody();
        if (!\is_array($data) || !isset($data[self::PARAM])) {
            return false;
        }

        $token = base64_decode((string) $data[self::PARAM], true);
        if ($token === false) {
            return false;
        }

        return $this->sessionManager->getCsrfToken()->isValid($token);
    }

    private function insertCsrfIntoPostForms(ResponseInterface $response): ResponseInterface
    {
        $this->sessionManager->getCsrfToken()->generateValue();

        $html = (string) $response->getBody();
        $token = rtrim(base64_encode($this->sessionManager->getCsrfToken()->getValue()), '=');
        $token = htmlentities($token, ENT_QUOTES, 'UTF-8');

        $replace = fn(array $match): string => $match[0]
            . '<input type="hidden" name="' . self::PARAM . '" value="' . $token . '">';

        $html = preg_replace_callback(
            '/(<form\s[^>]*method=["\']?POST["\']?[^>]*>)/i',
            $replace,
            $html,
            -1,
            $count,
        );

        if ($count === 0) {
            return $response;
        }

        $body = new Stream('php://temp', 'r+');
        $body->write((string) $html);

        return $response->withBody($body);
    }
}
