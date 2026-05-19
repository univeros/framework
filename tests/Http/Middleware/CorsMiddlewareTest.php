<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Middleware\CorsMiddleware;
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\Constants\CorsResponseHeaders;
use Neomerx\Cors\Strategies\Settings;
use PHPUnit\Framework\Attributes\DataProvider;

class CorsMiddlewareTest extends AbstractMiddlewareTest
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $settings = (new Settings())
            ->setServerOrigin([
                'scheme' => 'http',
                'host' => 'example.com',
                'port' => 123,
            ])
            ->setRequestAllowedOrigins([
                'http://good.example.com:321' => true,
                'http://evil.example.com:123' => null,
                CorsResponseHeaders::VALUE_ALLOW_ORIGIN_ALL => null,
                CorsResponseHeaders::VALUE_ALLOW_ORIGIN_NULL => null,
            ])
            ->setRequestAllowedMethods([
                'GET' => true,
                'PATCH' => null,
                'POST' => true,
                'PUT' => null,
                'DELETE' => true,
            ])
            ->setRequestAllowedHeaders([
                'content-type' => true,
                'some-disabled-header' => null,
                'x-enabled-custom-header' => true,
            ])
            ->setResponseExposedHeaders([
                'Content-Type' => true,
                'X-Custom-Header' => true,
                'X-Disabled-Header' => null,
            ])
            ->setRequestCredentialsSupported(false)
            ->setPreFlightCacheMaxAge(0)
            ->setForceAddAllowedMethodsToPreFlightResponse(true)
            ->setForceAddAllowedHeadersToPreFlightResponse(true)
            ->setCheckHost(true);

        $this->analyzer = Analyzer::instance($settings);

        parent::setUp();
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    public static function corsProvider(): array
    {
        return [
            ['http://not-valid.com:321', 403],
            ['http://example.com:123', 200],
        ];
    }

    #[DataProvider('corsProvider')]
    public function testCors(string $url, int $statusCode): void
    {
        $middleware = new CorsMiddleware($this->analyzer, $this->responseFactory());

        $response = $this->execute([$middleware], $url);

        $this->assertSame($statusCode, $response->getStatusCode());
    }
}
