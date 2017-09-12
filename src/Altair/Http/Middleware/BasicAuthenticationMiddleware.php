<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Traits\HttpAuthenticationAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class BasicAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait;

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $host = $request->getUri()->getHost();
        $scheme = $request->getUri()->getScheme();
        $params = $request->getServerParams();

        if (!$this->shouldAuthenticateRequest($request)) {
            return $next($request, $response);
        }

        $this->checkAllowance($host, $scheme);

        list($user, $password) = $this->getAuthDataFromServerParams($params);
        if (false === call_user_func($this->identityValidator, ['user' => $user, 'password' => $password])) {
            $response = $response
                ->withStatus(HttpStatusCodeInterface::HTTP_UNAUTHORIZED)
                ->withHeader('WWW-Authenticate', sprintf('Basic realm="%s"', $this->realm));

            if (is_callable($this->onError)) {
                $callableResponse = call_user_func_array(
                    $this->onError,
                    [$request, $response, ['message' => 'Authentication failed.']]
                );

                return $callableResponse instanceof ResponseInterface
                    ? $callableResponse
                    : $response;
            }
        }

        return $next($request, $response);
    }

    /**
     * Returns username and password from server parameters.
     *
     * @param array $params
     *
     * @return array
     */
    protected function getAuthDataFromServerParams(array $params): array
    {
        if (isset($params[$this->environment])) { /* PHP in CGI mode */
            // @see https://tools.ietf.org/html/rfc2617#page-5
            if (preg_match('/Basic\s+(.*)$/i', $params[$this->environment], $matches)) {
                return explode(":", base64_decode($matches[1]), 2);
            }
        } else {
            return [$params['PHP_AUTH_USER']?? null, $params['PHP_AUTH_PWD']?? null];
        }
    }
}
