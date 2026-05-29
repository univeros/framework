<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Index;

use Altair\Mcp\Attribute\McpTool;
use Override;

/**
 * Read-only wrapper over `bin/altair index:callers-of` — the call sites of a
 * method, each with the calling scope. Static, `self::`, `parent::`, and
 * `$this->` calls are linked; calls on untyped variables are not.
 */
#[McpTool(
    name: 'framework__callers',
    description: 'List the call sites of a method (e.g. "App\\\\User\\\\CreateUser::__invoke").',
    inputSchema: __DIR__ . '/../../Schema/callers-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class CallersTool extends IndexTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return $this->runIndex('index:callers-of', [$this->string($input, 'method') ?? '']);
    }
}
