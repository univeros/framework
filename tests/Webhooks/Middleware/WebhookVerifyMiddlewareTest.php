<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Middleware;

use Altair\Tests\Webhooks\Fixtures\StaticSecretResolver;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Middleware\WebhookVerifyMiddleware;
use Altair\Webhooks\Signing\HmacSha256Signer;
use Altair\Webhooks\Storage\InMemoryDeduplicator;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(WebhookVerifyMiddleware::class)]
final class WebhookVerifyMiddlewareTest extends TestCase
{
    private const string SECRET = 'whsec_test';
    private const string SECRET_NAME = 'stripe';
    private const string BODY = '{"id":"evt_1","type":"order.created"}';

    public function testRejectsWhenSignatureHeaderAbsent(): void
    {
        $response = $this->middleware()->process($this->request(headers: []), $this->handler());

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('verification failed', (string) $response->getBody());
    }

    public function testRejectsWhenSignatureMismatch(): void
    {
        $headers = ['X-Signature' => 'deadbeef', 'X-Timestamp' => (string) time(), 'X-Event-Id' => 'evt_1'];

        $response = $this->middleware()->process($this->request(headers: $headers), $this->handler());

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRejectsWhenTimestampMissingAndRequired(): void
    {
        $headers = ['X-Signature' => $this->sign(self::BODY), 'X-Event-Id' => 'evt_1'];

        $response = $this->middleware()->process($this->request(headers: $headers), $this->handler());

        self::assertSame(400, $response->getStatusCode());
    }

    public function testAllowsMissingTimestampWhenNotRequired(): void
    {
        $headers = ['X-Signature' => $this->sign(self::BODY), 'X-Event-Id' => 'evt_1'];
        $handler = $this->handler();

        $response = $this->middleware(requireTimestamp: false)->process($this->request(headers: $headers), $handler);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testRejectsTimestampOutsideWindowInThePast(): void
    {
        $headers = [
            'X-Signature' => $this->sign(self::BODY),
            'X-Timestamp' => (string) (time() - 1000),
            'X-Event-Id' => 'evt_1',
        ];

        $response = $this->middleware()->process($this->request(headers: $headers), $this->handler());

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('outside replay window', (string) $response->getBody());
    }

    public function testRejectsTimestampOutsideWindowInTheFuture(): void
    {
        $headers = [
            'X-Signature' => $this->sign(self::BODY),
            'X-Timestamp' => (string) (time() + 1000),
            'X-Event-Id' => 'evt_1',
        ];

        $response = $this->middleware()->process($this->request(headers: $headers), $this->handler());

        self::assertSame(400, $response->getStatusCode());
    }

    public function testRejectsNonNumericTimestamp(): void
    {
        $headers = [
            'X-Signature' => $this->sign(self::BODY),
            'X-Timestamp' => 'not-a-number',
            'X-Event-Id' => 'evt_1',
        ];

        $response = $this->middleware()->process($this->request(headers: $headers), $this->handler());

        self::assertSame(400, $response->getStatusCode());
    }

    public function testFreshEventPassesThroughToHandler(): void
    {
        $handler = $this->handler();

        $response = $this->middleware()->process($this->request(headers: $this->validHeaders()), $handler);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testReplayReturns200WithReplayedHeaderAndSkipsHandler(): void
    {
        $middleware = $this->middleware();
        $dedupeAwareHandler = $this->handler();

        $first = $middleware->process($this->request(headers: $this->validHeaders()), $dedupeAwareHandler);
        $second = $middleware->process($this->request(headers: $this->validHeaders()), $dedupeAwareHandler);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame('true', $second->getHeaderLine('Webhook-Replayed'));
        self::assertSame('', (string) $second->getBody());
        self::assertSame(1, $dedupeAwareHandler->calls, 'handler invoked exactly once');
    }

    public function testHandlerThrowReleasesClaimSoRetryReprocesses(): void
    {
        $deduplicator = new InMemoryDeduplicator();
        $middleware = $this->middleware(deduplicator: $deduplicator);

        $throwing = new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('boom');
            }
        };

        try {
            $middleware->process($this->request(headers: $this->validHeaders()), $throwing);
            self::fail('expected exception to propagate');
        } catch (RuntimeException) {
            // expected
        }

        // Claim released: a retry with the same event id must reach a fresh handler.
        $handler = $this->handler();
        $response = $this->middleware(deduplicator: $deduplicator)->process(
            $this->request(headers: $this->validHeaders()),
            $handler,
        );

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testHandlerReturning5xxReleasesClaim(): void
    {
        $deduplicator = new InMemoryDeduplicator();

        $first = $this->middleware(deduplicator: $deduplicator)->process(
            $this->request(headers: $this->validHeaders()),
            $this->handler(status: 503),
        );
        self::assertSame(503, $first->getStatusCode());

        $retryHandler = $this->handler();
        $second = $this->middleware(deduplicator: $deduplicator)->process(
            $this->request(headers: $this->validHeaders()),
            $retryHandler,
        );

        self::assertSame(201, $second->getStatusCode());
        self::assertSame(1, $retryHandler->calls);
    }

    public function testSyntheticEventIdDedupesWhenHeaderAbsent(): void
    {
        $middleware = $this->middleware();
        $ts = (string) time();
        $headers = ['X-Signature' => $this->sign(self::BODY), 'X-Timestamp' => $ts];
        $handler = $this->handler();

        $first = $middleware->process($this->request(headers: $headers), $handler);
        $second = $middleware->process($this->request(headers: $headers), $handler);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testBodyIsReadableByDownstreamHandler(): void
    {
        $capturing = new class implements RequestHandlerInterface {
            public string $seenBody = '';

            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->seenBody = (string) $request->getBody();

                return new Response();
            }
        };

        $this->middleware()->process($this->request(headers: $this->validHeaders()), $capturing);

        self::assertSame(self::BODY, $capturing->seenBody);
    }

    /**
     * @param array<string, string> $headers
     */
    private function middleware(
        array $headers = [],
        bool $requireTimestamp = true,
        ?InMemoryDeduplicator $deduplicator = null,
    ): WebhookVerifyMiddleware {
        return new WebhookVerifyMiddleware(
            signer: new HmacSha256Signer(),
            secrets: $this->secrets(),
            deduplicator: $deduplicator ?? new InMemoryDeduplicator(),
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
            secretName: self::SECRET_NAME,
            requireTimestamp: $requireTimestamp,
        );
    }

    private function secrets(): SecretResolverInterface
    {
        return new StaticSecretResolver(self::SECRET);
    }

    private function sign(string $body): string
    {
        return (new HmacSha256Signer())->sign($body, self::SECRET);
    }

    /**
     * @return array<string, string>
     */
    private function validHeaders(): array
    {
        return [
            'X-Signature' => $this->sign(self::BODY),
            'X-Timestamp' => (string) time(),
            'X-Event-Id' => 'evt_1',
        ];
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(array $headers): ServerRequestInterface
    {
        $request = new ServerRequest(serverParams: [], uploadedFiles: [], uri: '/webhooks/stripe', method: 'POST');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody((new StreamFactory())->createStream(self::BODY));
    }

    private function handler(int $status = 201): CountingHandler
    {
        return new CountingHandler($status);
    }
}
