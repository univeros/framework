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
 * `GET ?` renders the card overview; `GET ?panel=<id>` renders that panel's
 * detail view (a filterable table of its rows, 404 when the id is unknown).
 * Access is gated by the Observatory facade — a denied guard returns the 403
 * "disabled" page instead of any panel data. The host routes a path
 * (e.g. /_observatory) to this handler.
 *
 * $streamUrl, when set, is passed to the activity detail view to drive the SSE
 * live-tail (it should point at the host's {@see ActivityStreamHandler} route).
 */
final readonly class DashboardHandler implements RequestHandlerInterface
{
    public function __construct(
        private Observatory $observatory,
        private TemplateRenderer $renderer,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private string $streamUrl = '',
    ) {}

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->observatory->isAccessible()) {
            return $this->html(403, $this->renderer->render('denied'));
        }

        $dashboard = $this->observatory->dashboard();
        $panel = $request->getQueryParams()['panel'] ?? null;
        $panelId = \is_string($panel) ? $panel : '';

        if ($panelId !== '') {
            return $this->html(isset($dashboard[$panelId]) ? 200 : 404, $this->renderer->render('panel', [
                'panels' => $dashboard,
                'active' => $panelId,
                'streamUrl' => $this->streamUrl,
            ]));
        }

        return $this->html(200, $this->renderer->render('dashboard', [
            'panels' => $dashboard,
            'active' => '',
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
