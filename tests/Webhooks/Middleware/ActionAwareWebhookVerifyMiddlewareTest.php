<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Middleware;

use Altair\Tests\Webhooks\Fixtures\StaticSecretResolver;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Middleware\ActionAwareWebhookVerifyMiddleware;
use Altair\Webhooks\Signing\HmacSha256Signer;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Storage\InMemoryDeduplicator;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(ActionAwareWebhookVerifyMiddleware::class)]
final class ActionAwareWebhookVerifyMiddlewareTest extends TestCase
{
    private const string SECRET = 'whsec_test';

    private const string BODY = '{"id":"evt_1"}';

    public function testPassesThroughWhenNoActionAttribute(): void
    {
        $handler = new CountingHandler(200);

        $response = $this->middleware()->process($this->request(), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testPassesThroughWhenActionHasNoWebhookMethod(): void
    {
        $action = new class {};
        $handler = new CountingHandler(200);

        $response = $this->middleware()->process($this->request($action), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testPassesThroughWhenDirectionIsOutbound(): void
    {
        $action = new class {
            /** @return array<string, string> */
            public static function webhook(): array
            {
                return ['direction' => 'out', 'signing' => 'hmac-sha256'];
            }
        };
        $handler = new CountingHandler(200);

        $response = $this->middleware()->process($this->request($action), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    public function testVerifiesInboundAndRejectsMissingSignature(): void
    {
        $action = $this->inboundAction();
        $handler = new CountingHandler(201);

        $response = $this->middleware()->process($this->request($action), $handler);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(0, $handler->calls, 'handler not reached on a rejected webhook');
    }

    public function testVerifiesInboundAndPassesValidSignature(): void
    {
        $action = $this->inboundAction();
        $handler = new CountingHandler(201);
        $headers = [
            'X-Signature' => (new HmacSha256Signer())->sign(self::BODY, self::SECRET),
            'X-Timestamp' => (string) time(),
            'X-Event-Id' => 'evt_1',
        ];

        $response = $this->middleware()->process($this->request($action, $headers), $handler);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(1, $handler->calls);
    }

    private function inboundAction(): object
    {
        return new class {
            /** @return array<string, string> */
            public static function webhook(): array
            {
                return [
                    'direction' => 'in',
                    'signing' => 'hmac-sha256',
                    'secret_name' => 'stripe',
                    'dedupe_ttl' => '1h',
                    'timestamp_window' => '5m',
                    'signature_header' => 'X-Signature',
                    'timestamp_header' => 'X-Timestamp',
                    'event_id_header' => 'X-Event-Id',
                ];
            }
        };
    }

    private function middleware(): ActionAwareWebhookVerifyMiddleware
    {
        return new ActionAwareWebhookVerifyMiddleware(
            signers: SignerRegistry::default(),
            secrets: $this->secrets(),
            deduplicator: new InMemoryDeduplicator(),
            responseFactory: new ResponseFactory(),
            streamFactory: new StreamFactory(),
        );
    }

    private function secrets(): SecretResolverInterface
    {
        return new StaticSecretResolver(self::SECRET);
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(?object $action = null, array $headers = []): ServerRequestInterface
    {
        $request = new ServerRequest(serverParams: [], uploadedFiles: [], uri: '/webhooks/stripe', method: 'POST');
        if ($action !== null) {
            $request = $request->withAttribute('altair:http:action', $action);
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody((new StreamFactory())->createStream(self::BODY));
    }
}
