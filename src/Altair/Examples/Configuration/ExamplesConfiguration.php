<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Examples\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\ExampleParser;
use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\IndexBuilder;
use Override;

/**
 * Wires the examples library into the Altair Container.
 *
 * - {@see ExamplesSettings} is parsed once from environment variables
 * - {@see ExampleRepositoryInterface} resolves to a filesystem-backed
 *   {@see ExampleRepository} rooted at `<project>/.altair/examples/`
 *   (overridable via `ALTAIR_EXAMPLES_*` env vars)
 * - {@see IndexBuilder} is shared so CLI / MCP / programmatic callers
 *   all see the same instance
 */
final readonly class ExamplesConfiguration implements ConfigurationInterface
{
    public function __construct(
        private ?string $projectRoot = null,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $projectRoot = $this->projectRoot;

        $container->factory(
            ExamplesSettings::class,
            static fn(Env $env): ExamplesSettings => ExamplesSettings::fromEnv($env, $projectRoot),
        )->shared();

        $container->factory(
            ExampleParser::class,
            static fn(): ExampleParser => new ExampleParser(),
        )->shared();

        $container->factory(
            ExampleRepositoryInterface::class,
            static fn(ExamplesSettings $settings, ExampleParser $parser): ExampleRepositoryInterface
                => new ExampleRepository($settings->libraryPath(), $parser),
        )->shared();

        $container->factory(
            IndexBuilder::class,
            static fn(ExampleRepositoryInterface $repository): IndexBuilder => new IndexBuilder($repository),
        )->shared();
    }
}
