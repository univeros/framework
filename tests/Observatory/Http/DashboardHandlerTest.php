<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory\Http;

use Altair\Observatory\Http\DashboardHandler;
use Altair\Observatory\Observatory;
use Altair\Observatory\Panel\RuntimePanel;
use Altair\Observatory\PanelRegistry;
use Altair\Observatory\Security\EnvironmentAccessGuard;
use Altair\Observatory\View\TemplateRenderer;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardHandler::class)]
final class DashboardHandlerTest extends TestCase
{
    public function testRendersDashboardWhenAccessible(): void
    {
        $response = $this->handler(enabled: true)->handle(new ServerRequest());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('Observatory', $body);
        self::assertStringContainsString('Runtime', $body);
        self::assertStringContainsString('data-status="ok"', $body);
    }

    public function testReturns403WhenDenied(): void
    {
        $response = $this->handler(enabled: false)->handle(new ServerRequest());

        self::assertSame(403, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringContainsString('disabled', $body);
        // No panel data (e.g. the Runtime panel's headline/label) is rendered on the denied page.
        self::assertStringNotContainsString('Runtime', $body);
        self::assertStringNotContainsString('PHP ' . PHP_VERSION, $body);
    }

    private function handler(bool $enabled): DashboardHandler
    {
        $observatory = new Observatory(
            new PanelRegistry([new RuntimePanel()]),
            new EnvironmentAccessGuard($enabled, 'local'),
        );

        return new DashboardHandler(
            $observatory,
            TemplateRenderer::default(),
            new ResponseFactory(),
            new StreamFactory(),
        );
    }
}
