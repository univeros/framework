<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Discovery;

use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Support\ProjectContext;
use Override;

#[McpTool(
    name: 'framework__list_packages',
    description: 'List every installed univeros/* package with its one-line description.',
    inputSchema: __DIR__ . '/../../Schema/no-args.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ListPackagesTool implements McpToolInterface
{
    public function __construct(private ProjectContext $context) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $packages = [];
        foreach (glob($this->context->altairSrcDir . '/*/composer.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!\is_array($data)) {
                continue;
            }

            $name = $data['name'] ?? null;
            $description = $data['description'] ?? null;

            $packages[] = [
                'name' => \is_string($name) ? $name : 'univeros/' . strtolower(basename(\dirname($file))),
                'description' => \is_string($description) ? $description : '',
            ];
        }

        usort($packages, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['packages' => $packages, 'count' => \count($packages)];
    }
}
