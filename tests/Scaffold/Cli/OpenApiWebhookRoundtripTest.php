<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\OpenApiImportOptions;
use Altair\Scaffold\Cli\OpenApiImportRunner;
use Altair\Scaffold\Cli\OpenApiRoundtripOptions;
use Altair\Scaffold\Cli\OpenApiRoundtripRunner;
use Altair\Scaffold\Cli\RoundtripDifference;
use Altair\Scaffold\Emitter\EmittedFile;
use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Parser as SpecParser;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class OpenApiWebhookRoundtripTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-webhook-rt-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->sandbox);
    }

    public function testEmitterWritesInboundWebhookExtension(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUserWithInboundWebhook());
        $doc = Yaml::parse($file->contents);

        $webhook = $doc['paths']['/users']['post']['x-altair-webhook'];
        self::assertSame('in', $webhook['direction']);
        self::assertSame('hmac-sha256', $webhook['signing']);
        self::assertSame('stripe', $webhook['secret_name']);
        self::assertSame('Stripe-Signature', $webhook['header']);
        self::assertSame('24h', $webhook['dedupe_ttl']);
        // Defaults are omitted to keep the extension minimal + round-trip stable.
        self::assertArrayNotHasKey('timestamp_header', $webhook);
        self::assertArrayNotHasKey('timestamp_window', $webhook);
        self::assertArrayNotHasKey('retry', $webhook);
        self::assertArrayNotHasKey('dead_letter', $webhook);
    }

    public function testEmitterWritesOutboundWebhookExtension(): void
    {
        $file = (new OpenApiEmitter())->emit(SpecFixture::createUserWithOutboundWebhook());
        $doc = Yaml::parse($file->contents);

        $webhook = $doc['paths']['/users']['post']['x-altair-webhook'];
        self::assertSame('out', $webhook['direction']);
        self::assertSame('ed25519', $webhook['signing']);
        self::assertSame('webhook.deadletter', $webhook['dead_letter']);
        self::assertSame(['max_attempts' => 8, 'backoff' => 'linear'], $webhook['retry']);
        // base_delay matched its default, so it does not appear in the retry block.
        self::assertArrayNotHasKey('base_delay', $webhook['retry']);
    }

    public function testEmitterOmitsEveryDefaultForMinimalWebhook(): void
    {
        $spec = (new SpecParser())->parseString(<<<'YAML'
            endpoint: { method: POST, path: /hooks, summary: Receive, tags: [hooks] }
            domain: { class: App\Hook\Receive }
            webhook: { direction: in, signing: hmac-sha256 }
            YAML);

        $file = (new OpenApiEmitter())->emit($spec);
        $webhook = Yaml::parse($file->contents)['paths']['/hooks']['post']['x-altair-webhook'];

        self::assertSame(['direction' => 'in', 'signing' => 'hmac-sha256'], $webhook);
    }

    public function testImporterReadsWebhookExtensionBackIntoSpec(): void
    {
        $documentPath = $this->writeDocument(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /webhooks/stripe:
                post:
                  operationId: receiveStripe
                  x-altair-webhook:
                    direction: in
                    signing: hmac-sha256
                    secret_name: stripe
                    header: Stripe-Signature
                    dedupe_ttl: 24h
                  responses:
                    '200':
                      description: OK
                      content:
                        application/json:
                          schema: { type: object, properties: { ok: { type: boolean } } }
            YAML);

        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $documentPath,
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok, 'import should succeed; got: ' . $receipt->error);
        $spec = Yaml::parseFile($this->onlyGeneratedSpec());
        self::assertArrayHasKey('webhook', $spec);
        self::assertSame('in', $spec['webhook']['direction']);
        self::assertSame('hmac-sha256', $spec['webhook']['signing']);
        self::assertSame('stripe', $spec['webhook']['secret_name']);
        self::assertSame('Stripe-Signature', $spec['webhook']['header']);
        self::assertSame('24h', $spec['webhook']['dedupe_ttl']);
    }

    public function testRoundtripPreservesInboundWebhook(): void
    {
        // NB: extension key order MUST match OpenApiEmitter::renderWebhook's
        // insertion order — the gate compares blocks with strict `===`.
        $documentPath = $this->writeDocument(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /webhooks/stripe:
                post:
                  operationId: receiveStripe
                  x-altair-webhook:
                    direction: in
                    signing: hmac-sha256
                    secret_name: stripe
                    header: Stripe-Signature
                    dedupe_ttl: 24h
                  responses:
                    '200':
                      description: OK
                      content:
                        application/json:
                          schema: { type: object, properties: { ok: { type: boolean } } }
            YAML);

        $receipt = (new OpenApiRoundtripRunner())->run(new OpenApiRoundtripOptions($documentPath));

        self::assertTrue($receipt->clean, 'round-trip should preserve x-altair-webhook; diff: ' . $receipt->toJson());
    }

    public function testRoundtripPreservesOutboundWebhookWithRetry(): void
    {
        $documentPath = $this->writeDocument(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /posts:
                post:
                  operationId: createPost
                  x-altair-webhook:
                    direction: out
                    signing: ed25519
                    retry:
                      max_attempts: 8
                      backoff: linear
                    dead_letter: webhook.deadletter
                  responses:
                    '201':
                      description: Created
                      content:
                        application/json:
                          schema: { type: object, properties: { id: { type: string } } }
            YAML);

        $receipt = (new OpenApiRoundtripRunner())->run(new OpenApiRoundtripOptions($documentPath));

        self::assertTrue($receipt->clean, 'round-trip should preserve outbound x-altair-webhook; diff: ' . $receipt->toJson());
    }

    public function testRoundtripGateFlagsExtensionDriftWhenWebhookDropped(): void
    {
        $documentPath = $this->writeDocument(<<<'YAML'
            openapi: 3.1.0
            info: { title: X, version: 1.0 }
            paths:
              /webhooks/stripe:
                post:
                  operationId: receiveStripe
                  x-altair-webhook:
                    direction: in
                    signing: hmac-sha256
                    secret_name: stripe
                  responses:
                    '200':
                      description: OK
                      content:
                        application/json:
                          schema: { type: object, properties: { ok: { type: boolean } } }
            YAML);

        // A deliberately broken emitter that forgets to write the extension:
        // the gate must catch the regression as extension_drift.
        $brokenEmitter = new class extends OpenApiEmitter {
            public function emit(Spec $spec): EmittedFile
            {
                $file = parent::emit($spec);
                /** @var array<string, mixed> $doc */
                $doc = Yaml::parse($file->contents);
                $paths = $doc['paths'];
                foreach ($paths as $path => $methods) {
                    foreach ($methods as $method => $operation) {
                        unset($operation['x-altair-webhook']);
                        $paths[$path][$method] = $operation;
                    }
                }

                $doc['paths'] = $paths;

                return new EmittedFile(
                    relativePath: $file->relativePath,
                    contents: Yaml::dump($doc, 8, 2, Yaml::DUMP_OBJECT_AS_MAP),
                    kind: $file->kind,
                );
            }
        };

        $receipt = (new OpenApiRoundtripRunner(openApiEmitter: $brokenEmitter))
            ->run(new OpenApiRoundtripOptions($documentPath));

        self::assertFalse($receipt->clean, 'dropping x-altair-webhook must fail the gate');

        $drift = array_filter(
            $receipt->differences,
            static fn (RoundtripDifference $d): bool => $d->kind === RoundtripDifference::KIND_EXTENSION_DRIFT
                && str_contains($d->pointer, 'x-altair-webhook'),
        );
        self::assertNotEmpty($drift, 'expected an extension_drift difference on x-altair-webhook; got: ' . $receipt->toJson());
    }

    /**
     * The importer derives the spec path from the operation path; rather than
     * hard-code the deriver's resource-dir rule, find the single emitted spec.
     */
    private function onlyGeneratedSpec(): string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sandbox . '/api', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $node) {
            if ($node->isFile() && str_ends_with((string) $node->getFilename(), '.yaml')) {
                return $node->getPathname();
            }
        }

        self::fail('no generated spec file found under api/');
    }

    private function writeDocument(string $yaml): string
    {
        $path = $this->sandbox . '/openapi-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }

    private function removeRecursively(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $node) {
            if ($node->isDir()) {
                @rmdir($node->getPathname());
            } else {
                @unlink($node->getPathname());
            }
        }

        @rmdir($path);
    }
}
