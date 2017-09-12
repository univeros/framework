<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpAuthRuleInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Traits\HttpAuthenticationAwareTrait;
use Altair\Http\Validator\DigestSignatureValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class DigestAuthenticationMiddleware implements MiddlewareInterface
{
    use HttpAuthenticationAwareTrait {
        __construct as init; /* rename to be able to override constructor and still use it from trait */
    }

    /**
     * @var string Digest Authentication only attribute.
     */
    protected $nonce;

    /**
     * DigestAuthenticationMiddleware constructor.
     *
     * @param DigestSignatureValidator $identityValidator
     * @param HttpAuthRuleInterface[] $rules
     * @param array $options
     */
    public function __construct(DigestSignatureValidator $identityValidator, array $rules = null, array $options = null)
    {
        $this->init($identityValidator, $rules, $options);

        $this->nonce = $options['nonce']?? null;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $host = $request->getUri()->getHost();
        $scheme = $request->getUri()->getScheme();

        if (!$this->shouldAuthenticateRequest($request)) {
            return $next($request, $response);
        }

        $this->checkAllowance($host, $scheme);

        $authorization = $this->parseAuthorizationHeader($request);

        if ($authorization) {
            $arguments = [
                'authorization' => $authorization,
                'realm' => $this->realm,
                'method' => $request->getMethod()
            ];
            if (true === call_user_func($this->identityValidator, $arguments)) {
                return $next(
                    $request->withAttribute(MiddlewareInterface::ATTRIBUTE_USERNAME, $authorization['username']),
                    $response
                );
            }
        }

        return $response
            ->withStatus(401)
            ->withHeader(
                'WWW-Authenticate',
                'Digest realm="' . $this->realm . '",qop="auth",nonce="' .
                ($this->nonce ?: uniqid()) . '",opaque="' . md5($this->realm) . '"'
            );
    }

    /**
     * Parses the header for a basic authentication.
     *
     * @param ServerRequestInterface $request
     *
     * @return array|null
     */
    protected function parseAuthorizationHeader(ServerRequestInterface $request): ?array
    {
        $header = $request->getHeaderLine('Authorization');

        if (strpos($header, 'Digest') !== 0) {
            return null;
        }

        $parts = ['nonce', 'nc', 'cnonce', 'qop', 'username', 'uri', 'response'];
        $data = [];

        preg_match_all(
            '@(' . implode('|', array_values($parts)) . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@',
            substr($header, 7),
            $matches,
            PREG_SET_ORDER
        );

        $parts = array_flip($parts);

        if ($matches) {
            foreach ($matches as $match) {
                $data[$match[1]] = $match[3]?? $match[4];
                unset($parts[$match[1]]);
            }
        }

        return empty($parts) ? $data : null;
    }
}
