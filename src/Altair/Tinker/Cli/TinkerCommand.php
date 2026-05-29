<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Container\Container;
use Altair\Tinker\Contracts\ReplInterface;
use Altair\Tinker\Preamble\PreambleBuilder;
use Altair\Tinker\Repl\PsyShellRepl;
use Altair\Tinker\Repl\ReplContext;

/**
 * `bin/altair tinker` — drop into an interactive PsySH shell with the DI
 * container in scope, after printing a doctor-style summary of what is wired.
 *
 * The dependencies are nullable so the command works in a bare `bin/altair`
 * (a fresh container in scope, an empty preamble) and is enriched when a host
 * applies {@see \Altair\Tinker\Configuration\TinkerConfiguration} (the real
 * booted container and the introspection-backed counts). Exit code is `2` when
 * PsySH is not installed, otherwise whatever the shell returns.
 */
#[Command(
    name: 'tinker',
    description: 'Interactive REPL with the container in scope (PsySH).',
)]
final readonly class TinkerCommand
{
    public function __construct(
        private ?ReplInterface $repl = null,
        private ?PreambleBuilder $preamble = null,
        private ?ReplContext $context = null,
    ) {}

    public function __invoke(
        #[Option(description: 'Override the command history file.', name: 'history-file')]
        ?string $historyFile = null,
    ): int {
        $repl = $this->repl ?? new PsyShellRepl();
        if (!$repl->isAvailable()) {
            echo 'PsySH is not installed. Add it with `composer require --dev psy/psysh` '
                . "(it ships with dev installs and the standalone univeros/tinker package).\n";

            return 2;
        }

        $context = $this->context ?? new ReplContext(scopeVariables: ['container' => new Container()]);
        if ($historyFile !== null && $historyFile !== '') {
            $context = new ReplContext($context->scopeVariables, $historyFile, $context->historySize);
        }

        $preamble = $this->preamble ?? new PreambleBuilder();

        return $repl->run($context, $preamble->build($context));
    }
}
