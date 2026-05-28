<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Rule;

use Altair\Suggest\Contracts\SuggestionRuleInterface;
use Altair\Suggest\Result\Severity;
use Altair\Suggest\Result\Suggestion;
use Altair\Suggest\Snapshot\BindingNode;
use Altair\Suggest\Snapshot\Snapshot;
use Override;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A concrete binding that nothing else references: no other binding's
 * constructor depends on it, no route dispatches to it, no pipeline includes
 * it, no event listens through it. A candidate for removal.
 *
 * Deliberately `info` and hedged. Framework entry points are *meant* to have
 * no inbound DI edges — controllers and middleware are invoked by the runtime,
 * commands by the CLI — so PSR-15 middleware/handlers and route actions are
 * exempted, and the message tells the reader to confirm it is not an entry
 * point before deleting.
 */
final readonly class DeadBindingRule implements SuggestionRuleInterface
{
    /** @var list<string> Binding kinds that represent a constructable service. */
    private const array CONCRETE_KINDS = ['class', 'share', 'definition', 'delegate'];

    #[Override]
    public function name(): string
    {
        return 'dead_binding';
    }

    #[Override]
    public function analyse(Snapshot $snapshot): array
    {
        $referenced = $this->referencedIdentifiers($snapshot);

        $out = [];
        foreach ($snapshot->bindings as $binding) {
            if (!$this->isCandidate($binding)) {
                continue;
            }

            if ($this->isReferenced($binding, $referenced)) {
                continue;
            }

            $out[] = new Suggestion(
                rule: $this->name(),
                severity: Severity::Info,
                subject: $binding->id,
                message: \sprintf('%s is bound but nothing references it — it may be dead code.', $binding->id),
                fix: 'Confirm it is not an entry point (controller, command, subscriber), then remove the binding.',
            );
        }

        return $out;
    }

    private function isCandidate(BindingNode $binding): bool
    {
        if (!\in_array($binding->kind, self::CONCRETE_KINDS, true)) {
            return false;
        }

        return !$binding->implements(MiddlewareInterface::class)
            && !$binding->implements(RequestHandlerInterface::class);
    }

    /**
     * Every identifier that is "used" by something in the snapshot, normalised
     * for case-insensitive comparison. Aliases are followed one hop so that
     * depending on an interface also marks its concrete target as referenced.
     *
     * @return array<string, true>
     */
    private function referencedIdentifiers(Snapshot $snapshot): array
    {
        $referenced = [];

        foreach ($snapshot->bindings as $binding) {
            foreach ($binding->dependencies as $dependency) {
                $referenced[$this->normalise($dependency)] = true;
            }
        }

        foreach ($snapshot->routes as $route) {
            $referenced[$this->normalise($this->classOf($route->action))] = true;
        }

        foreach ($snapshot->middleware as $middleware) {
            $referenced[$this->normalise($middleware)] = true;
        }

        foreach ($snapshot->events as $event) {
            foreach ($event->listenerTargets as $target) {
                $referenced[$this->normalise($target)] = true;
            }
        }

        // Follow alias edges to a fixed point: a referenced alias id pulls its
        // target in, which may itself be a referenced alias. Looping until
        // stable avoids depending on the order bindings happen to be emitted in.
        do {
            $changed = false;
            foreach ($snapshot->bindings as $binding) {
                $target = $this->normalise($binding->target);
                if (isset($referenced[$this->normalise($binding->id)]) && !isset($referenced[$target])) {
                    $referenced[$target] = true;
                    $changed = true;
                }
            }
        } while ($changed);

        return $referenced;
    }

    /**
     * @param array<string, true> $referenced
     */
    private function isReferenced(BindingNode $binding, array $referenced): bool
    {
        return isset($referenced[$this->normalise($binding->id)])
            || isset($referenced[$this->normalise($binding->target)]);
    }

    private function classOf(string $callable): string
    {
        $class = strstr($callable, '::', true);

        return $class === false ? $callable : $class;
    }

    private function normalise(string $identifier): string
    {
        return strtolower(ltrim($identifier, '\\'));
    }
}
