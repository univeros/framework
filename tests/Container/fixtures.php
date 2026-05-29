<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Container;

use Altair\Container\Attribute\Autowire;
use Altair\Container\Attribute\Factory;
use Altair\Container\Attribute\Inject;
use Altair\Container\Attribute\Lazy;
use Altair\Container\Attribute\Tag;

interface LoggerInterface
{
    public function channel(): string;
}

final class FileLogger implements LoggerInterface
{
    public function channel(): string
    {
        return 'file';
    }
}

final class NullLogger implements LoggerInterface
{
    public function channel(): string
    {
        return 'null';
    }
}

final class NoDeps
{
    public bool $built = true;
}

final class Dependency
{
    public string $marker = 'dep';
}

final class NeedsDependency
{
    public function __construct(public readonly Dependency $dependency) {}
}

final class NeedsLogger
{
    public function __construct(public readonly LoggerInterface $logger) {}
}

final class InjectConsumer
{
    public function __construct(
        #[Inject(FileLogger::class)]
        public readonly LoggerInterface $logger,
    ) {}
}

final class AutowireServiceConsumer
{
    public function __construct(
        #[Autowire(service: NullLogger::class)]
        public readonly LoggerInterface $logger,
    ) {}
}

final class AutowireParamConsumer
{
    public function __construct(
        #[Autowire(param: 'app.locale')]
        public readonly string $locale,
    ) {}
}

#[Factory(WidgetFactory::class)]
final class Widget
{
    public function __construct(public readonly string $label) {}
}

final class WidgetFactory
{
    public function __invoke(): Widget
    {
        return new Widget('factory-made');
    }
}

interface ReporterInterface
{
    public function name(): string;
}

#[Tag('reporters')]
final class AlphaReporter implements ReporterInterface
{
    public function name(): string
    {
        return 'alpha';
    }
}

final class BetaReporter implements ReporterInterface
{
    public function name(): string
    {
        return 'beta';
    }
}

final class Heavy
{
    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public function ping(): string
    {
        return 'pong';
    }
}

final class TypedVariadic
{
    /**
     * @var list<Dependency>
     */
    public readonly array $deps;

    public function __construct(Dependency ...$deps)
    {
        $this->deps = array_values($deps);
    }
}

final class NullableInterfaceCtor
{
    public function __construct(public readonly ?LoggerInterface $logger) {}
}

final class DefaultCtor
{
    public function __construct(public readonly int $count = 5) {}
}

final class CycleA
{
    public function __construct(public readonly CycleB $b) {}
}

final class CycleB
{
    public function __construct(public readonly CycleA $a) {}
}

final class Greeter
{
    public function __invoke(Dependency $dependency): string
    {
        return 'hello-' . $dependency->marker;
    }

    public function greet(Dependency $dependency): string
    {
        return 'greet-' . $dependency->marker;
    }

    public static function shout(Dependency $dependency): string
    {
        return 'shout-' . $dependency->marker;
    }
}

final class OverridableConsumer
{
    public function __construct(public readonly LoggerInterface $logger) {}
}

#[Lazy]
final class LazyMarked
{
    public function ping(): string
    {
        return 'lazy';
    }
}
