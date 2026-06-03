<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Logging\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Override;
use Psr\Log\LoggerInterface;

/**
 * Binds a Monolog-backed PSR-3 logger into the Altair Container.
 *
 * Follows the same "wrap a battle-tested library behind a contract" pattern as
 * the Persistence (Cycle) and Messaging (Symfony Messenger) packages — here the
 * contract is the industry-standard {@see LoggerInterface}, which the framework
 * already types against in the Http, Events, Courier, Messaging and Cache
 * packages. Before this binding those all fall back to a NullLogger.
 *
 * Place this configuration early in the chain so packages that register a
 * NullLogger fallback (e.g. Messaging) see a real logger already bound. The
 * `LoggerInterface` binding is unconditional: opting into LoggingConfiguration
 * means you want this logger. A host can still override it by binding its own
 * `LoggerInterface` after this configuration runs.
 */
final readonly class LoggingConfiguration implements ConfigurationInterface
{
    #[Override]
    public function apply(Container $container): void
    {
        $container->factory(
            LoggingSettings::class,
            static fn(Env $env): LoggingSettings => LoggingSettings::fromEnv($env),
        )->shared();

        $container->factory(
            Logger::class,
            static fn(LoggingSettings $settings): Logger => self::createLogger($settings),
        )->shared();

        $container->factory(
            LoggerInterface::class,
            static fn(Container $c): LoggerInterface => $c->get(Logger::class),
        )->shared();
    }

    /**
     * Build a Monolog logger from settings. Exposed (not inlined) so it can be
     * unit-tested without standing up the container or touching the environment.
     */
    public static function createLogger(LoggingSettings $settings): Logger
    {
        $handler = new StreamHandler($settings->path, self::resolveLevel($settings->level));
        $handler->setFormatter(
            $settings->isJson()
                ? new JsonFormatter()
                : new LineFormatter(null, null, true, true),
        );

        return new Logger($settings->channel, [$handler]);
    }

    /**
     * Map a PSR-3 level name to a Monolog level, defaulting to Debug when the
     * configured value is not a recognised level.
     */
    private static function resolveLevel(string $level): Level
    {
        return match (strtolower(trim($level))) {
            'emergency' => Level::Emergency,
            'alert' => Level::Alert,
            'critical' => Level::Critical,
            'error' => Level::Error,
            'warning' => Level::Warning,
            'notice' => Level::Notice,
            'info' => Level::Info,
            default => Level::Debug,
        };
    }
}
