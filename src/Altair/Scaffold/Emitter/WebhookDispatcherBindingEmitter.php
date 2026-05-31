<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Ast\WebhookSpec;
use Altair\Scaffold\Templating\PhpHeader;
use LogicException;

/**
 * Emits an outbound webhook dispatcher binding for a spec carrying
 * `webhook: { direction: out }`.
 *
 * The generated class wraps {@see \Altair\Webhooks\Dispatcher\WebhookDispatcher}
 * with the signing scheme, retry policy, and dead-letter transport declared in
 * the spec, pre-filling the signer when the host dispatches. Lives under
 * `app/Webhooks/` — a clear namespace for these bindings.
 */
class WebhookDispatcherBindingEmitter
{
    public function __construct(private readonly Naming $naming = new Naming()) {}

    public function emit(Spec $spec): EmittedFile
    {
        $webhook = $spec->webhook;
        if (!$webhook instanceof WebhookSpec) {
            throw new LogicException('WebhookDispatcherBindingEmitter requires a webhook block.');
        }

        $shortName = $this->naming->webhookDispatcherShortName($spec);
        $namespace = $this->namespaceOf($this->naming->webhookDispatcherFqcn($spec));

        $signing = var_export($webhook->signing, true);
        $backoff = var_export($webhook->retryBackoff, true);
        $deadLetter = var_export($webhook->deadLetterTransport, true);
        $maxAttempts = $webhook->retryMaxAttempts;
        $baseDelaySeconds = $this->toSeconds($webhook->retryBaseDelay);

        $header = PhpHeader::render($namespace);
        $body = <<<PHP
            use Altair\\Webhooks\\Dispatcher\\RetryPolicy;
            use Altair\\Webhooks\\Dispatcher\\WebhookDispatcher;
            use Altair\\Webhooks\\Storage\\Delivery;

            /**
             * Generated outbound webhook binding for {$spec->endpoint->method} {$spec->endpoint->path}.
             *
             * Wraps WebhookDispatcher with the signing scheme + retry policy the
             * spec declared; the host dispatches through this binding.
             */
            final readonly class {$shortName}
            {
                public const string SIGNING = {$signing};

                public const ?string DEAD_LETTER_TRANSPORT = {$deadLetter};

                public function __construct(private WebhookDispatcher \$dispatcher) {}

                public function retryPolicy(): RetryPolicy
                {
                    return new RetryPolicy(maxAttempts: {$maxAttempts}, backoff: {$backoff}, baseDelaySeconds: {$baseDelaySeconds});
                }

                /**
                 * @param array<array-key, mixed>|string \$payload
                 */
                public function dispatch(string \$eventName, array|string \$payload, string \$subscriberUrl, string \$secretName): Delivery
                {
                    return \$this->dispatcher->dispatch(\$eventName, \$payload, \$subscriberUrl, \$secretName, self::SIGNING);
                }
            }

            PHP;

        return new EmittedFile(
            relativePath: $this->naming->webhookDispatcherPath($spec),
            contents: $header . $body,
            kind: EmittedFileKind::WebhookDispatcher,
        );
    }

    private function toSeconds(string $duration): int
    {
        if (preg_match('/^(\d+)(ms|s|m|h|d)$/', $duration, $match) !== 1) {
            return 30;
        }

        $value = (int) $match[1];

        return match ($match[2]) {
            'ms' => $value > 0 ? max(1, (int) ceil($value / 1000)) : 0,
            'm' => $value * 60,
            'h' => $value * 3_600,
            'd' => $value * 86_400,
            default => $value,
        };
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }
}
