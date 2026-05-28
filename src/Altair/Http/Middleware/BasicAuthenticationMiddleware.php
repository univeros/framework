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
use Altair\Http\Contracts\IdentityValidatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Traits\HttpAuthenticationAwareTrait;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BasicAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait {
        __construct as private initAuthentication;
    }

    /**
     * @param list<HttpAuthRuleInterface>|null $rules
     * @param array<string, mixed>|null        $options
     */
    public function __construct(
        IdentityValidatorInterface $identityValidator,
        private readonly ResponseFactoryInterface $responseFactory,
        ?array $rules = null,
        ?array $options = null,
    ) {
        $this->initAuthentication($identityValidator, $rules, $options);
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->shouldAuthenticateRequest($request)) {
            return $handler->handle($request);
        }

        $this->checkAllowance($request->getUri()->getHost(), $request->getUri()->getScheme());

        [$user, $password] = $this->getAuthDataFromServerParams($request->getServerParams());

        if (\call_user_func($this->identityValidator, ['user' => $user, 'password' => $password])) {
            return $handler->handle($request);
        }

        $response = $this->responseFactory
            ->createResponse(HttpStatusCodeInterface::HTTP_UNAUTHORIZED)
            ->withHeader('WWW-Authenticate', \sprintf('Basic realm="%s"', $this->realm));

        if (\is_callable($this->onError)) {
            $callableResponse = ($this->onError)($request, $response, ['message' => 'Authentication failed.']);
            if ($callableResponse instanceof ResponseInterface) {
                return $callableResponse;
            }
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function getAuthDataFromServerParams(array $params): array
    {
        if (isset($params[$this->environment])) {
            if (preg_match('/Basic\s+(.*)$/i', (string) $params[$this->environment], $matches)) {
                $decoded = explode(':', (string) base64_decode($matches[1], true), 2);

                return [$decoded[0] ?? null, $decoded[1] ?? null];
            }

            return [null, null];
        }

        return [$params['PHP_AUTH_USER'] ?? null, $params['PHP_AUTH_PWD'] ?? null];
    }
}
