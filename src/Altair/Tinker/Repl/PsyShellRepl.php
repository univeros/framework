<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Repl;

use Altair\Tinker\Contracts\ReplInterface;
use Override;
use Psy\Shell;

/**
 * The PsySH-backed REPL. The only interactive part of the package.
 *
 * `build()` produces a fully configured {@see Shell} (scope variables, history,
 * casters, startup banner) without blocking — it is unit-tested. `run()` is the
 * thin, untestable line that hands control to the interactive shell.
 */
final readonly class PsyShellRepl implements ReplInterface
{
    public function __construct(
        private PsyConfigurationFactory $configurations = new PsyConfigurationFactory(),
    ) {}

    #[Override]
    public function isAvailable(): bool
    {
        return class_exists(Shell::class);
    }

    public function build(ReplContext $context, string $banner): Shell
    {
        $shell = new Shell($this->configurations->create($context, $banner));
        $shell->setScopeVariables($context->scopeVariables);

        return $shell;
    }

    #[Override]
    public function run(ReplContext $context, string $banner): int
    {
        return $this->build($context, $banner)->run();
    }
}
