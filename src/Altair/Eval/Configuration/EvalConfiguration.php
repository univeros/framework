<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Eval\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Eval\Evaluator;
use Altair\Eval\Runner\SubprocessRunner;
use Altair\Eval\Runner\WrapperBuilder;
use Override;

/**
 * Wires a shared {@see Evaluator} into the Container.
 *
 * Optional: the `eval` CLI command builds a default Evaluator when none is
 * bound. This Configuration is for hosts (and the MCP server) that want a
 * specific PHP binary path (e.g. PHP-FPM-only environments where `php` does
 * not resolve to the CLI binary) or want to share the Evaluator with the rest
 * of their container.
 */
final readonly class EvalConfiguration implements ConfigurationInterface
{
    public function __construct(private string $phpBinary = 'php') {}

    #[Override]
    public function apply(Container $container): void
    {
        $binary = $this->phpBinary;

        $container->factory(
            Evaluator::class,
            static fn(): Evaluator => new Evaluator(
                new SubprocessRunner(),
                new WrapperBuilder(),
                $binary,
            ),
        )->shared();
    }
}
