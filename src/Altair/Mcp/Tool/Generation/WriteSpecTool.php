<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Generation;

use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\ProjectContext;
use Altair\Scaffold\Spec\SpecLoader;
use Override;
use Throwable;

#[McpTool(
    name: 'framework__write_spec',
    description: 'Create or update a YAML spec file. The content is validated before it is written.',
    inputSchema: __DIR__ . '/../../Schema/write-spec-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class WriteSpecTool implements McpToolInterface
{
    public function __construct(
        private ProjectContext $context,
        private PathGuard $guard,
        private ServerMode $mode,
        private EventLog $events,
        private SpecLoader $loader = new SpecLoader(),
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        if (!$this->mode->allowsFileMutation()) {
            throw new GuardrailException('Server is in readonly mode; spec writes are disabled.');
        }

        $path = \is_string($input['path'] ?? null) ? $input['path'] : '';
        $content = \is_string($input['content'] ?? null) ? $input['content'] : '';

        $this->guard->assertWritable($path);
        $this->validate($content);

        $absolute = str_starts_with($path, '/') ? $path : $this->context->path($path);
        $existed = is_file($absolute);

        $directory = \dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new McpException(\sprintf("Cannot create directory '%s'.", $directory));
        }

        if (file_put_contents($absolute, $content) === false) {
            throw new McpException(\sprintf("Failed to write '%s'.", $path));
        }

        $this->events->record(EventKind::ManualEdit, EventStatus::Ok, 'mcp framework__write_spec ' . $path);

        return ['path' => $path, 'action' => $existed ? 'updated' : 'created', 'bytes' => \strlen($content)];
    }

    /**
     * Validate the YAML by loading it through the real spec loader from a
     * temp file, so a malformed spec is rejected before touching the target.
     */
    private function validate(string $content): void
    {
        $base = tempnam(sys_get_temp_dir(), 'mcp-spec-') ?: throw new McpException('Cannot create temp file for validation.');
        $temp = $base . '.yaml';

        try {
            file_put_contents($temp, $content);
            $this->loader->load($temp);
        } catch (Throwable $throwable) {
            throw new McpException('Spec is invalid: ' . $throwable->getMessage(), 0, $throwable);
        } finally {
            @unlink($base);
            @unlink($temp);
        }
    }
}
