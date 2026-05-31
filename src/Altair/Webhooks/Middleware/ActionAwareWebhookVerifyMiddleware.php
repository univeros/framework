<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Middleware;

use Altair\Webhooks\Contracts\InboundDeduplicatorInterface;
use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Signing\SignerRegistry;
use Altair\Webhooks\Support\DurationParser;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Auto-wires inbound webhook verification from the resolved Action's spec
 * metadata.
 *
 * Reads the resolved Action (via the request attribute that the HTTP
 * dispatcher publishes; default `altair:http:action`) and, when the Action
 * exposes a static `webhook()` accessor with `direction === 'in'` (the one the
 * scaffolder generates for inbound specs), constructs a per-request
 * {@see WebhookVerifyMiddleware} configured from that policy and delegates.
 *
 * Pass-through when there is no Action, no `webhook()` accessor, or the policy
 * is outbound — so adding it to the stack is safe for endpoints that have not
 * opted into inbound verification.
 */
final readonly class ActionAwareWebhookVerifyMiddleware implements MiddlewareInterface
{
    /** Matches `Altair\Http\Contracts\MiddlewareInterface::ATTRIBUTE_ACTION`. */
    public const string DEFAULT_ACTION_ATTRIBUTE = 'altair:http:action';

    public function __construct(
        private SignerRegistry $signers,
        private SecretResolverInterface $secrets,
        private InboundDeduplicatorInterface $deduplicator,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private DurationParser $durations = new DurationParser(),
        private string $actionAttribute = self::DEFAULT_ACTION_ATTRIBUTE,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $policy = $this->resolvePolicy($request);
        if ($policy === null) {
            return $handler->handle($request);
        }

        $middleware = new WebhookVerifyMiddleware(
            signer: $this->signers->get($policy['signing']),
            secrets: $this->secrets,
            deduplicator: $this->deduplicator,
            responseFactory: $this->responseFactory,
            streamFactory: $this->streamFactory,
            secretName: $policy['secret_name'],
            dedupeTtlSeconds: $this->durations->toSeconds($policy['dedupe_ttl']),
            timestampWindowSeconds: $this->durations->toSeconds($policy['timestamp_window']),
            signatureHeader: $policy['signature_header'],
            timestampHeader: $policy['timestamp_header'],
            eventIdHeader: $policy['event_id_header'],
        );

        return $middleware->process($request, $handler);
    }

    /**
     * @return ?array{signing: string, secret_name: string, dedupe_ttl: string, timestamp_window: string, signature_header: string, timestamp_header: string, event_id_header: string}
     */
    private function resolvePolicy(ServerRequestInterface $request): ?array
    {
        $action = $request->getAttribute($this->actionAttribute);
        if (!\is_object($action) || !method_exists($action, 'webhook')) {
            return null;
        }

        /** @var mixed $policy */
        $policy = $action::webhook();
        if (!\is_array($policy)) {
            return null;
        }

        if (($policy['direction'] ?? null) !== 'in') {
            return null;
        }

        $signing = $this->stringField($policy, 'signing');
        $secretName = $this->stringField($policy, 'secret_name');
        if ($signing === null || $secretName === null) {
            return null;
        }

        return [
            'signing' => $signing,
            'secret_name' => $secretName,
            'dedupe_ttl' => $this->stringField($policy, 'dedupe_ttl') ?? '1h',
            'timestamp_window' => $this->stringField($policy, 'timestamp_window') ?? '5m',
            'signature_header' => $this->stringField($policy, 'signature_header') ?? 'X-Signature',
            'timestamp_header' => $this->stringField($policy, 'timestamp_header') ?? 'X-Timestamp',
            'event_id_header' => $this->stringField($policy, 'event_id_header') ?? 'X-Event-Id',
        ];
    }

    /**
     * @param array<array-key, mixed> $policy
     */
    private function stringField(array $policy, string $key): ?string
    {
        $value = $policy[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : null;
    }
}
