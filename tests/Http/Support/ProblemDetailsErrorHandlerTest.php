<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Support;

use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Contracts\ProblemExtensionInterface;
use Altair\Http\Exception\HttpException;
use Altair\Http\Exception\HttpNotFoundException;
use Altair\Http\Exception\InputValidationException;
use Altair\Http\Support\ProblemDetailsErrorHandler;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class ProblemDetailsErrorHandlerTest extends TestCase
{
    public function testRendersProblemJsonForNotFound(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('application/json', '/missing', new HttpNotFoundException('No route for /missing')),
            new Response('php://memory', 404),
        );

        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));

        $problem = $this->decode($response);
        $this->assertSame('about:blank', $problem['type']);
        $this->assertSame('Not Found', $problem['title']);
        $this->assertSame(404, $problem['status']);
        $this->assertSame('No route for /missing', $problem['detail']);
        $this->assertSame('/missing', $problem['instance']);
    }

    public function testProductionHidesServerErrorMessageAndTrace(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(debug: false),
            $this->request('application/json', '/x', new RuntimeException('secret connection string')),
            new Response('php://memory', 500),
        );

        $problem = $this->decode($response);
        $this->assertSame(500, $problem['status']);
        $this->assertSame('An unexpected error occurred.', $problem['detail']);
        $this->assertArrayNotHasKey('exception', $problem);
        $this->assertArrayNotHasKey('trace', $problem);
        $this->assertStringNotContainsString('secret connection string', (string) $response->getBody());
    }

    public function testDebugExposesExceptionAndTrace(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(debug: true),
            $this->request('application/json', '/x', new RuntimeException('boom detail')),
            new Response('php://memory', 500),
        );

        $problem = $this->decode($response);
        $this->assertSame('boom detail', $problem['detail']);
        $this->assertSame(RuntimeException::class, $problem['exception']);
        $this->assertIsArray($problem['trace']);
        $this->assertArrayHasKey('file', $problem);
    }

    public function testValidationErrorsBecomeProblemExtensions(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('application/json', '/users', new InputValidationException(['email' => 'invalid'])),
            new Response('php://memory', 422),
        );

        $problem = $this->decode($response);
        $this->assertSame(422, $problem['status']);
        $this->assertSame(['email' => 'invalid'], $problem['errors']);
    }

    public function testExtensionsCannotClobberReservedMembers(): void
    {
        $malicious = new class('x') extends HttpException implements ProblemExtensionInterface {
            public function getProblemExtensions(): array
            {
                return ['status' => 200, 'title' => 'OK', 'errors' => ['a' => 'b']];
            }
        };

        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('application/json', '/x', $malicious),
            new Response('php://memory', 403),
        );

        $problem = $this->decode($response);
        $this->assertSame(403, $problem['status']);
        $this->assertSame('Forbidden', $problem['title']);
        $this->assertSame(['a' => 'b'], $problem['errors']);
    }

    public function testHtmlOutputEscapesReflectedMessage(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('text/html', '/x', new HttpNotFoundException('<script>alert(1)</script>')),
            new Response('php://memory', 404),
        );

        $body = (string) $response->getBody();
        $this->assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;', $body);
    }

    public function testPlainTextNegotiation(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('text/plain', '/x', new HttpNotFoundException('gone')),
            new Response('php://memory', 404),
        );

        $this->assertStringStartsWith('text/plain', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('404 Not Found', (string) $response->getBody());
    }

    public function testWildcardAcceptDefaultsToJson(): void
    {
        $response = $this->handle(
            new ProblemDetailsErrorHandler(),
            $this->request('*/*', '/x', new HttpNotFoundException('gone')),
            new Response('php://memory', 404),
        );

        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    private function handle(
        ProblemDetailsErrorHandler $handler,
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        return $handler($request, $response);
    }

    private function request(string $accept, string $path, ?Throwable $exception): ServerRequestInterface
    {
        return (new ServerRequest())
            ->withUri(new Uri($path))
            ->withHeader('Accept', $accept)
            ->withAttribute(MiddlewareInterface::ATTRIBUTE_EXCEPTION, $exception);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
