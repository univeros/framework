<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Discovery;

use Altair\Cli\Attribute\Command as CommandAttribute;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\PhpClassScanner;
use Altair\Mcp\Support\ProjectContext;

use const GLOB_ONLYDIR;

use Override;
use ReflectionClass;

#[McpTool(
    name: 'framework__list_commands',
    description: 'List every bin/altair CLI command registered by the framework packages.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ListCommandsTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PhpClassScanner $scanner = new PhpClassScanner(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $commands = [];
        foreach (glob($this->context->altairSrcDir . '/*/Cli', GLOB_ONLYDIR) ?: [] as $cliDir) {
            foreach ($this->scanner->classesIn($cliDir) as $class) {
                $command = $this->describe($class);
                if ($command !== null) {
                    $commands[] = $command;
                }
            }
        }

        usort($commands, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['commands' => $commands, 'count' => \count($commands)];
    }

    /**
     * @param class-string $class
     *
     * @return array{name: string, description: string, class: string}|null
     */
    private function describe(string $class): ?array
    {
        if (!class_exists($class)) {
            return null;
        }

        $attributes = (new ReflectionClass($class))->getAttributes(CommandAttribute::class);
        if ($attributes === []) {
            return null;
        }

        $attribute = $attributes[0]->newInstance();

        return ['name' => $attribute->name, 'description' => $attribute->description, 'class' => $class];
    }
}
