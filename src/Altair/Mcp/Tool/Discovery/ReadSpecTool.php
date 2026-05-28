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
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Support\ProjectContext;
use Override;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[McpTool(
    name: 'framework__read_spec',
    description: 'Read one YAML spec: raw text plus its parsed structure.',
    inputSchema: __DIR__ . '/../../Schema/read-spec-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class ReadSpecTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PathGuard $guard,
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $path = \is_string($input['path'] ?? null) ? $input['path'] : '';
        $this->guard->assertWithinRoot($path);
        $absolute = str_starts_with($path, '/') ? $path : $this->context->path($path);

        if (!is_file($absolute)) {
            throw new McpException(\sprintf("Spec file '%s' does not exist.", $path));
        }

        $raw = (string) file_get_contents($absolute);

        try {
            $parsed = Yaml::parse($raw);
        } catch (ParseException $parseException) {
            return ['path' => $path, 'raw' => $raw, 'parsed' => null, 'parse_error' => $parseException->getMessage()];
        }

        return ['path' => $path, 'raw' => $raw, 'parsed' => $parsed];
    }
}
