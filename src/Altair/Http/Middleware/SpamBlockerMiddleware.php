<?php
namespace Altair\Http\Middleware;

use Altair\Http\Contracts\HttpStatusCodeInterface;
use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SpamBlockerMiddleware implements MiddlewareInterface
{
    protected $list;

    /**
     * SpamBlockerMiddleware constructor.
     *
     * @param string $path the spammers domain list
     */
    public function __construct(string $path)
    {
        if (!is_file($path)) {
            throw new RuntimeException(sprintf('The spammers file "%s" does not exists.', $path));
        }
        $this->list = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    /**
     * @inheritdoc
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $referer = parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST);
        $referer = preg_replace('/^(www\.)/i', '', $referer);

        return in_array($referer, $this->list, true)
            ? $response->withStatus(HttpStatusCodeInterface::HTTP_FORBIDDEN)
            : $next($request, $response);
    }
}
