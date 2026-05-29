<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Preamble;

use Altair\Introspection\Inspector\ContainerInspector;
use Altair\Introspection\Inspector\ListenerInspector;
use Altair\Introspection\Inspector\RouteInspector;
use Altair\Tinker\Repl\ReplContext;
use Throwable;

/**
 * Builds the doctor-style startup banner: what is in scope, and a count of the
 * project's bindings / routes / listeners so you know what you can poke at.
 *
 * The introspection inspectors are optional — `univeros/introspection` is a
 * suggested dependency, and even when present the counts are only meaningful
 * once the host has shared its collections. Any inspector that is missing or
 * cannot read its source degrades to a hint rather than failing the REPL.
 */
final readonly class PreambleBuilder
{
    public function __construct(
        private ?ContainerInspector $containerInspector = null,
        private ?RouteInspector $routeInspector = null,
        private ?ListenerInspector $listenerInspector = null,
    ) {}

    public function build(ReplContext $context): string
    {
        $lines = ['<info>Altair Tinker</info> — interactive REPL. Ctrl+D to exit, `help` for PsySH commands.', ''];

        $lines[] = 'In scope:';
        foreach ($context->scopeVariableNames() as $name) {
            $lines[] = \sprintf('  $%-12s %s', $name, $this->describeScopeVariable($name));
        }

        $lines[] = '';
        $lines[] = 'Wired:';
        $lines[] = $this->countLine('bindings', $this->count($this->containerInspector));
        $lines[] = $this->countLine('routes', $this->count($this->routeInspector));
        $lines[] = $this->countLine('listeners', $this->count($this->listenerInspector));

        if (!$this->containerInspector instanceof ContainerInspector) {
            $lines[] = '';
            $lines[] = '  (counts need the host to apply IntrospectionConfiguration and share its collections)';
        }

        return implode("\n", $lines) . "\n";
    }

    private function describeScopeVariable(string $name): string
    {
        return match ($name) {
            'container' => 'the DI container — resolve with $container->make(Foo::class)',
            default => '',
        };
    }

    private function countLine(string $label, ?int $count): string
    {
        return \sprintf('  %-10s %s', $label, $count === null ? '—' : (string) $count);
    }

    private function count(ContainerInspector|RouteInspector|ListenerInspector|null $inspector): ?int
    {
        if ($inspector === null) {
            return null;
        }

        try {
            return \count($inspector->inspectAll()->rows);
        } catch (Throwable) {
            return null;
        }
    }
}
