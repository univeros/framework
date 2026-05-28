<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Traits\HttpAuthenticationAwareTrait;
use Altair\Http\Validator\DigestSignatureValidator;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DigestAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait {
        __construct as private initAuthentication;
    }

    private readonly ?string $nonce;

    /**
     * @param list<HttpAuthRuleInterface>|null $rules
     * @param array<string, mixed>|null        $options
     */
    public function __construct(
        DigestSignatureValidator $identityValidator,
        private readonly ResponseFactoryInterface $responseFactory,
        ?array $rules = null,
        ?array $options = null,
    ) {
        $this->initAuthentication($identityValidator, $rules, $options);
        $this->nonce = $options['nonce'] ?? null;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->shouldAuthenticateRequest($request)) {
            return $handler->handle($request);
        }

        $this->checkAllowance($request->getUri()->getHost(), $request->getUri()->getScheme());

        $authorization = $this->parseAuthorizationHeader($request);

        if ($authorization !== null) {
            $arguments = [
                'authorization' => $authorization,
                'realm' => $this->realm,
                'method' => $request->getMethod(),
            ];
            if (\call_user_func($this->identityValidator, $arguments)) {
                return $handler->handle(
                    $request->withAttribute(MiddlewareInterface::ATTRIBUTE_USERNAME, $authorization['username']),
                );
            }
        }

        return $this->responseFactory
            ->createResponse(HttpStatusCodeInterface::HTTP_UNAUTHORIZED)
            ->withHeader('WWW-Authenticate', \sprintf(
                'Digest realm="%s",qop="auth",nonce="%s",opaque="%s"',
                $this->realm,
                $this->nonce ?? uniqid(),
                md5($this->realm),
            ));
    }

    /**
     * @return array<string, string>|null
     */
    private function parseAuthorizationHeader(ServerRequestInterface $request): ?array
    {
        $header = $request->getHeaderLine('Authorization');

        if (!str_starts_with($header, 'Digest')) {
            return null;
        }

        $parts = ['nonce', 'nc', 'cnonce', 'qop', 'username', 'uri', 'response'];
        $data = [];

        preg_match_all(
            '@(' . implode('|', $parts) . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@',
            substr($header, 7),
            $matches,
            PREG_SET_ORDER,
        );

        $expected = array_flip($parts);

        foreach ($matches as $match) {
            $data[$match[1]] = $match[3] ?? $match[4] ?? '';
            unset($expected[$match[1]]);
        }

        return $expected === [] ? $data : null;
    }
}
