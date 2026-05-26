<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Middleware\CorsMiddleware;
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Strategies\Settings;
use PHPUnit\Framework\Attributes\DataProvider;

class CorsMiddlewareTest extends AbstractMiddlewareTest
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $settings = (new Settings())
            ->setServerOrigin('http', 'example.com', 123)
            ->setAllowedOrigins([
                'http://good.example.com:321',
            ])
            ->setAllowedMethods(['GET', 'PATCH', 'POST', 'PUT', 'DELETE'])
            ->setAllowedHeaders(['content-type', 'x-enabled-custom-header'])
            ->setExposedHeaders(['Content-Type', 'X-Custom-Header'])
            ->setCredentialsNotSupported()
            ->setPreFlightCacheMaxAge(0)
            ->enableCheckHost();

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
