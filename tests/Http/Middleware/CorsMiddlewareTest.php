<?php

namespace Altair\Tests\Http\Middleware;

use Altair\Http\Middleware\CorsMiddleware;
use Neomerx\Cors\Analyzer;
use Neomerx\Cors\Contracts\Constants\CorsResponseHeaders;
use Neomerx\Cors\Strategies\Settings;

class CorsMiddlewareTest extends AbstractMiddlewareTest
{
    private $analyzer;

    protected function setUp()
    {
        $settings = (new Settings())
            ->setServerOrigin([
                'scheme' => 'http',
                'host' => 'example.com',
                'port' => '123'
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

    public function corsProvider()
    {
        return [
            ['http://not-valid.com:321', 403],
            ['http://example.com:123', 200],
        ];
    }

    /**
     * @dataProvider corsProvider
     * @param mixed $url
     * @param mixed $statusCode
     */
    public function testCors($url, $statusCode)
    {
        $middleware = new CorsMiddleware($this->analyzer);

        $response = $this->execute([$middleware], $url);

        $this->assertEquals($statusCode, $response->getStatusCode());
    }
}
