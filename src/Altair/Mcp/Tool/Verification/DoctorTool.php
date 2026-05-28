<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Verification;

use Altair\Doctor\Doctor;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Override;

#[McpTool(
    name: 'framework__doctor',
    description: 'Run the project health checks and return a structured report.',
    inputSchema: __DIR__ . '/../../Schema/doctor-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class DoctorTool implements McpToolInterface
{
    public function __construct(private Doctor $doctor) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        return $this->doctor->run($this->stringList($input['only'] ?? null), $this->stringList($input['skip'] ?? null))->toArray();
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, \is_string(...)));
    }
}
