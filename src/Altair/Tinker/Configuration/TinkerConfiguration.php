<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Tinker\Contracts\ReplInterface;
use Altair\Tinker\Preamble\PreambleBuilder;
use Altair\Tinker\Repl\PsyShellRepl;
use Altair\Tinker\Repl\ReplContext;
use Exception;
use Override;

/**
 * Wires the REPL with the host's real container in scope and an
 * introspection-backed preamble.
 *
 * The container instance is captured into the scope (the framework Container
 * does not self-inject, so a `Container`-typed dependency would otherwise be a
 * fresh empty instance). Route/listener counts come from whatever inspectors
 * the host has bound; the binding count uses the captured container directly.
 * History location/size come from `ALTAIR_TINKER_HISTORY_FILE` /
 * `ALTAIR_TINKER_HISTORY_SIZE` unless overridden in the constructor.
 */
final readonly class TinkerConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $historyFile = null,
        private int $historySize = 0,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $historyFile = $this->historyFile ?? $this->env('ALTAIR_TINKER_HISTORY_FILE', ReplContext::DEFAULT_HISTORY_FILE);
        $historySize = $this->historySize > 0 ? $this->historySize : (int) $this->env('ALTAIR_TINKER_HISTORY_SIZE', '0');

        $container
            ->delegate(
                ReplContext::class,
                static fn(): ReplContext => new ReplContext(
                    scopeVariables: ['container' => $container],
                    historyFile: $historyFile === '' ? null : $historyFile,
                    historySize: $historySize,
                ),
            )
            ->share(ReplContext::class)

            ->delegate(
                PreambleBuilder::class,
                static fn(): PreambleBuilder => new PreambleBuilder(
                    class_exists(ContainerInspector::class) ? new ContainerInspector($container) : null,
                    self::optional($container, RouteInspector::class),
                    self::optional($container, ListenerInspector::class),
                ),
            )
            ->share(PreambleBuilder::class)

            ->alias(ReplInterface::class, PsyShellRepl::class)
            ->share(PsyShellRepl::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private static function optional(Container $container, string $class): ?object
    {
        if (!class_exists($class)) {
            return null;
        }

        try {
            $instance = $container->make($class);

            return $instance instanceof $class ? $instance : null;
        } catch (Exception) {
            return null;
        }
    }

    private function env(string $key, string $default): string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? $default : $value;
    }
}
