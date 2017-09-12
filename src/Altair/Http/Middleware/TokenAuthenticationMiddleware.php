<?php

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\CredentialsExtractorInterface;
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
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class TokenAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait {
        __construct as init; /* rename to be able to override constructor and still use it from trait */
    }

    protected $tokenExtractor;
    protected $credentialsExtractor;
    protected $tokenFactory;

    /**
     * TokenAuthenticationMiddleware constructor.
     *
     * @param TokenExtractorInterface $tokenExtractor
     * @param CredentialsExtractorInterface $credentialsExtractor
     * @param TokenFactoryInterface $tokenFactory
     * @param IdentityValidatorInterface $identityValidator
     * @param array|null $rules
     * @param array|null $options
     */
    public function __construct(
        TokenExtractorInterface $tokenExtractor,
        CredentialsExtractorInterface $credentialsExtractor,
        TokenFactoryInterface $tokenFactory,
        IdentityValidatorInterface $identityValidator,
        array $rules = null,
        array $options = null
    ) {
        $this->init($identityValidator, $rules, $options);

        $this->tokenExtractor = $tokenExtractor;
        $this->credentialsExtractor = $credentialsExtractor;
        $this->tokenFactory = $tokenFactory;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if (!$this->shouldAuthenticateRequest($request)) {
            return $next($request, $response);
        }

        $this->checkAllowance($request->getUri()->getHost(), $request->getUri()->getScheme());

        $authToken = null;
        $exception = null;

        try {
            if ($token = $this->tokenExtractor->extract($request)) {
                $authToken = $this->tokenFactory->fromTokenString($token);
            } elseif ($credentials = $this->credentialsExtractor->extract($request)) {
                list($user, $password) = $credentials;
                if (false === call_user_func($this->identityValidator, ['user' => $user, 'password' => $password])) {
                    throw new AuthorizationException('Invalid credentials.');
                }
                $authToken = $this->tokenFactory->fromCredentials($credentials);
            } else {
                throw new AuthorizationException('No authentication token has been specified.');
            }
        } catch (InvalidTokenException $e) {
            $exception = $e;
            $response->withStatus(HttpStatusCodeInterface::HTTP_UNAUTHORIZED);
        } catch (AuthorizationTokenException $e) {
            $exception = $e;
            $response->withStatus(HttpStatusCodeInterface::HTTP_UNAUTHORIZED);
        } catch (AuthorizationException $e) {
            $exception = $e;
            $response->withStatus(HttpStatusCodeInterface::HTTP_FORBIDDEN);
        } catch (Exception $e) {
            throw $e;
        }

        if ($response->getStatusCode() === HttpStatusCodeInterface::HTTP_UNAUTHORIZED ||
            $response->getStatusCode() === HttpStatusCodeInterface::HTTP_FORBIDDEN) {
            if (is_callable($this->onError)) {
                $callableResponse = call_user_func_array(
                    $this->onError,
                    [$request, $response, $exception]
                );

                return $callableResponse instanceof ResponseInterface
                    ? $callableResponse
                    : $response;
            }
        }

        if ($authToken) {
            $request = $request->withAttribute(TokenInterface::TOKEN_KEY, $authToken);
        }

        return $next($request, $response);
    }
}
