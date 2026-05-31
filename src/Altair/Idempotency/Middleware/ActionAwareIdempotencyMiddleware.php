<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Middleware;

use Altair\Idempotency\Contracts\IdempotencyStoreInterface;
use Altair\Idempotency\Hash\RequestBodyHasher;
use Altair\Idempotency\Hash\TtlParser;
use Override;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Auto-wires the idempotency primitive from the resolved Action's
 * spec metadata.
 *
 * Reads the resolved Action (via the request attribute that
 * `DispatcherMiddleware` publishes; default attribute name
 * `altair:http:action` matches `Altair\Http\Contracts\MiddlewareInterface::ATTRIBUTE_ACTION`)
 * and, if the Action exposes a static `idempotency()` accessor (the
 * one #174's `ActionEmitter` generates), constructs a per-request
 * {@see IdempotencyKeyMiddleware} configured from that policy and
 * delegates to it.
 *
 * When no Action is on the request, or when the Action has no
 * `idempotency()` method, the middleware passes the request through
 * unchanged — so adding it to the stack is safe for endpoints that
 * have not opted into the primitive.
 *
 * The dependency on `univeros/http` is intentionally avoided by
 * configuring the attribute name via the constructor; the default
 * mirrors the value `univeros/http` ships so most hosts need no
 * argument at all.
 */
final readonly class ActionAwareIdempotencyMiddleware implements MiddlewareInterface
{
    /** Matches `Altair\Http\Contracts\MiddlewareInterface::ATTRIBUTE_ACTION`. */
    public const string DEFAULT_ACTION_ATTRIBUTE = 'altair:http:action';

    public function __construct(
        private IdempotencyStoreInterface $store,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private RequestBodyHasher $hasher = new RequestBodyHasher(),
        private TtlParser $ttlParser = new TtlParser(),
        private string $actionAttribute = self::DEFAULT_ACTION_ATTRIBUTE,
    ) {}

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $policy = $this->resolvePolicy($request);
        if ($policy === null) {
            return $handler->handle($request);
        }

        $middleware = new IdempotencyKeyMiddleware(
            store: $this->store,
            responseFactory: $this->responseFactory,
            streamFactory: $this->streamFactory,
            ttlSeconds: $this->ttlParser->toSeconds($policy['ttl']),
            mode: $policy['mode'],
            hasher: $this->hasher,
        );

        return $middleware->process($request, $handler);
    }

    /**
     * @return ?array{ttl: string, scope: string, mode: string}
     */
    private function resolvePolicy(ServerRequestInterface $request): ?array
    {
        $action = $request->getAttribute($this->actionAttribute);
        if (!\is_object($action)) {
            return null;
        }

        if (!method_exists($action, 'idempotency')) {
            return null;
        }

        /** @var mixed $policy */
        $policy = $action::idempotency();
        if (!\is_array($policy)) {
            return null;
        }

        if (!isset($policy['ttl'], $policy['scope'], $policy['mode'])) {
            return null;
        }

        if (!\is_string($policy['ttl']) || !\is_string($policy['scope']) || !\is_string($policy['mode'])) {
            return null;
        }

        return ['ttl' => $policy['ttl'], 'scope' => $policy['scope'], 'mode' => $policy['mode']];
    }
}
