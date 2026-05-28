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
use Altair\Mcp\Support\ProjectContext;
use FilesystemIterator;

use const GLOB_ONLYDIR;

use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

#[McpTool(
    name: 'framework__describe_package',
    description: 'Describe one univeros package: its purpose, contracts and concrete classes.',
    inputSchema: __DIR__ . '/../../Schema/describe-package-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DescribePackageTool implements McpToolInterface
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
        $requested = \is_string($input['package'] ?? null) ? $input['package'] : '';
        $directory = $this->locate($requested);

        if ($directory === null) {
            throw new McpException(\sprintf("Unknown package '%s'.", $requested));
        }

        $manifest = json_decode((string) file_get_contents($directory . '/composer.json'), true);
        $name = \is_array($manifest) && \is_string($manifest['name'] ?? null) ? $manifest['name'] : '';
        $description = \is_array($manifest) && \is_string($manifest['description'] ?? null) ? $manifest['description'] : '';

        return [
            'name' => $name,
            'description' => $description,
            'directory' => basename($directory),
            'contracts' => $this->classNames($directory . '/Contracts'),
            'classes' => $this->classNames($directory, recursive: true),
        ];
    }

    private function locate(string $requested): ?string
    {
        $short = strtolower(str_replace('univeros/', '', $requested));
        if ($short === '') {
            return null;
        }

        foreach (glob($this->context->altairSrcDir . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (strtolower(basename($directory)) === $short) {
                return $directory;
            }

            $manifest = $directory . '/composer.json';
            if (is_file($manifest)) {
                $data = json_decode((string) file_get_contents($manifest), true);
                if (\is_array($data) && \is_string($data['name'] ?? null) && strtolower($data['name']) === strtolower($requested)) {
                    return $directory;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function classNames(string $directory, bool $recursive = false): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $names = $recursive ? $this->recursivePhp($directory) : $this->directPhp($directory);

        $names = array_values(array_unique($names));
        sort($names);

        return $names;
    }

    /**
     * @return list<string>
     */
    private function directPhp(string $directory): array
    {
        $names = [];
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $names[] = basename($file, '.php');
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private function recursivePhp(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        $names = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $names[] = $file->getBasename('.php');
            }
        }

        return $names;
    }
}
