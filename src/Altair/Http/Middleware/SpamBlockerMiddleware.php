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
use Altair\Http\Exception\RuntimeException;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SpamBlockerMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private readonly array $list;

    /**
     * @param string $path the spammers domain list
     */
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        string $path,
    ) {
        if (!is_file($path)) {
            throw new RuntimeException(\sprintf('The spammers file "%s" does not exists.', $path));
        }

        $entries = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->list = $entries === false ? [] : $entries;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $referer = parse_url($request->getHeaderLine('Referer'), PHP_URL_HOST) ?: '';
        $referer = (string) preg_replace('/^(www\.)/i', '', $referer);

        return \in_array($referer, $this->list, true)
            ? $this->responseFactory->createResponse(HttpStatusCodeInterface::HTTP_FORBIDDEN)
            : $handler->handle($request);
    }
}
