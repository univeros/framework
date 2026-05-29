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
 * Read-only wrapper over `bin/altair index:find-usages`.
 *
 * Returns every recorded reference to a symbol (class, `Class::method`,
 * `Class::$prop`, or `Class::CONST`). An optional `kind` narrows the result to
 * a single usage kind (e.g. `call`, `type_hint`, `new`).
 */
#[McpTool(
    name: 'framework__find_usages',
    description: 'List every recorded usage of a symbol across the project (AST + spec aware). '
        . 'Pass a class, "Class::method", "Class::$prop", or "Class::CONST"; optional kind filters by usage kind.',
    inputSchema: __DIR__ . '/../../Schema/find-usages-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class FindUsagesTool extends IndexTool
{
    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        $symbol = $this->string($input, 'symbol') ?? '';
        $result = $this->runIndex('index:find-usages', [$symbol]);

        $kind = $this->string($input, 'kind');
        if ($kind !== null && isset($result['usages']) && \is_array($result['usages'])) {
            $result['usages'] = array_values(array_filter(
                $result['usages'],
                static fn(mixed $u): bool => \is_array($u) && ($u['usage_kind'] ?? null) === $kind,
            ));
            $result['count'] = \count($result['usages']);
        }

        return $result;
    }
}
