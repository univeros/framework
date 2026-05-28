<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Http;

use Altair\Observatory\Observatory;
use Altair\Observatory\View\TemplateRenderer;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Serves the Observatory dashboard as server-rendered HTML.
 *
 * Access is gated by the Observatory facade: when the guard denies, a 403
 * "disabled" page is returned instead of any panel data. The host routes a path
 * (e.g. /_observatory) to this handler.
 */
final readonly class DashboardHandler implements RequestHandlerInterface
{
    public function __construct(
        private Observatory $observatory,
        private TemplateRenderer $renderer,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->observatory->isAccessible()) {
            return $this->html(403, $this->renderer->render('denied'));
        }

        return $this->html(200, $this->renderer->render('dashboard', [
            'panels' => $this->observatory->dashboard(),
        ]));
    }

    private function html(int $status, string $body): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withBody($this->streamFactory->createStream($body));
    }
}
