<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\CredentialsExtractorInterface;
use Altair\Http\Contracts\HttpAuthRuleInterface;
use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\IdentityValidatorInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\TokenExtractorInterface;
use Altair\Http\Contracts\TokenFactoryInterface;
use Altair\Http\Contracts\TokenInterface;
use Altair\Http\Exception\AuthorizationException;
use Altair\Http\Exception\AuthorizationTokenException;
use Altair\Http\Exception\InvalidTokenException;
use Altair\Http\Traits\HttpAuthenticationAwareTrait;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class TokenAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait {
        __construct as private initAuthentication;
    }

    /**
     * @param list<HttpAuthRuleInterface>|null $rules
     * @param array<string, mixed>|null        $options
     */
    public function __construct(
        private readonly TokenExtractorInterface $tokenExtractor,
        private readonly CredentialsExtractorInterface $credentialsExtractor,
        private readonly TokenFactoryInterface $tokenFactory,
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

        $authToken = null;
        $exception = null;
        $statusCode = null;

        try {
            if ($token = $this->tokenExtractor->extract($request)) {
                $authToken = $this->tokenFactory->fromTokenString($token);
            } elseif ($credentials = $this->credentialsExtractor->extract($request)) {
                [$user, $password] = $credentials;
                if (\call_user_func($this->identityValidator, ['user' => $user, 'password' => $password]) === false) {
                    throw new AuthorizationException('Invalid credentials.');
                }

                $authToken = $this->tokenFactory->fromCredentials($credentials);
            } else {
                throw new AuthorizationException('No authentication token has been specified.');
            }
        } catch (InvalidTokenException | AuthorizationTokenException $e) {
            $exception = $e;
            $statusCode = HttpStatusCodeInterface::HTTP_UNAUTHORIZED;
        } catch (AuthorizationException $e) {
            $exception = $e;
            $statusCode = HttpStatusCodeInterface::HTTP_FORBIDDEN;
        } catch (Throwable $e) {
            throw $e;
        }

        if ($statusCode !== null) {
            $response = $this->responseFactory->createResponse($statusCode);
            if (\is_callable($this->onError)) {
                $callableResponse = ($this->onError)($request, $response, $exception);
                if ($callableResponse instanceof ResponseInterface) {
                    return $callableResponse;
                }
            }

            return $response;
        }

        if ($authToken instanceof TokenInterface) {
            $request = $request->withAttribute(TokenInterface::TOKEN_KEY, $authToken);
        }

        return $handler->handle($request);
    }
}
