<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Support\MimeType;
use Altair\Http\Traits\IpAddressAwareTrait;
use Altair\Session\SessionManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Stream;

class CsrfMiddleware implements MiddlewareInterface
{
    use IpAddressAwareTrait;

    /**
     * @var SessionManager
     */
    protected $sessionManager;
    /**
     * @var MimeType
     */
    protected $mimeType;
    /**
     * @var string
     */
    protected $param = '_csrf';

    /**
     * CsrfMiddleware constructor.
     *
     * @param SessionManager $sessionManager
     * @param MimeType $mimeType
     */
    public function __construct(SessionManager $sessionManager, MimeType $mimeType)
    {
        $this->sessionManager = $sessionManager;
        $this->mimeType = $mimeType;
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        if ((new MimeType())->getFromResponse($response) !== 'text/html') {
            return $next($request, $response);
        }

        if ($this->getIsPostRequest($request) && $this->validateCsrfTokenFromRequest($request)) {
            return $response->withStatus(HttpStatusCodeInterface::HTTP_FORBIDDEN);
        }

        return $this->insertCsrfIntoPostForms($next($request, $response));
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function getIsPostRequest(ServerRequestInterface $request): bool
    {
        return in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'CONNECT', 'TRACE', 'OPTIONS']) === false;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    protected function validateCsrfTokenFromRequest(ServerRequestInterface $request): bool
    {
        $data = $request->getParsedBody();
        if (!isset($data[$this->param])) {
            return false;
        }
        $token = base64_decode($data[$this->param]);

        return $this->sessionManager->getCsrfToken()->isValid($token);
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function insertCsrfIntoPostForms(ResponseInterface $response): ResponseInterface
    {
        $this->sessionManager->getCsrfToken()->generateValue();

        $html = (string)$response->getBody();
        $token = rtrim(base64_encode($this->sessionManager->getCsrfToken()->getValue()), '=');
        $token = htmlentities($token, ENT_QUOTES, 'UTF-8');

        $replace = function ($match) use ($token) {
            preg_match('/action=["\']?([^"\'\s]+)["\']?/i', $match[0], $matches);

            return $match[0] . '<input type="hidden" name="' . $this->param . '" value="' . $token . '">';
        };

        $html = preg_replace_callback('/(<form\s[^>]*method=["\']?POST["\']?[^>]*>)/i', $replace, $html, -1, $count);

        if (!empty($count)) {
            $body = new Stream('php://temp', 'r+');
            $body->write($html);

            return $response->withBody($body);
        }

        return $response;
    }
}
