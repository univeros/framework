<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Exception\ScaffoldException;

/**
 * `bin/altair openapi:roundtrip <document>` — verify that the
 * OpenAPI → Altair YAML → OpenAPI chain does not silently lose
 * operations or `x-altair-*` extensions.
 *
 * Mirrors the contract style of `spec:emit-sdk --check`: human or JSON
 * report, exit code 1 on drift in `--check` mode so CI gates can refuse
 * to merge. See [docs/openapi/roundtrip.md](../../docs/openapi/roundtrip.md)
 * for the normalization rules.
 */
#[Command(
    name: 'openapi:roundtrip',
    description: 'Detect drift in the OpenAPI → spec → OpenAPI round-trip.',
)]
final readonly class OpenApiRoundtripCommand
{
    public function __invoke(
        #[Argument(description: 'Path to the OpenAPI 3.1 YAML document.')]
        string $document,
        #[Option(description: 'Exit 1 on drift (CI gate).')]
        bool $check = false,
        #[Option(description: 'Output format (human|json).')]
        string $format = 'human',
    ): int {
        if ($format !== 'human' && $format !== 'json') {
            throw new ScaffoldException(\sprintf("--format='%s' is not supported. Use 'human' or 'json'.", $format));
        }

        $options = new OpenApiRoundtripOptions(
            documentPath: $document,
            check: $check,
        );

        $receipt = (new OpenApiRoundtripRunner())->run($options);

        if ($format === 'json') {
            echo $receipt->toJson() . PHP_EOL;
        } else {
            $this->renderHuman($receipt);
        }

        if ($receipt->error !== null) {
            return 1;
        }

        return $check && !$receipt->clean ? 1 : 0;
    }

    private function renderHuman(RoundtripReceipt $receipt): void
    {
        if ($receipt->error !== null) {
            echo \sprintf('openapi:roundtrip failed: %s%s', $receipt->error, PHP_EOL);

            return;
        }

        if ($receipt->clean) {
            echo \sprintf('clean: %d operation(s) round-tripped without drift.%s', $receipt->operationsCompared, PHP_EOL);

            return;
        }

        echo \sprintf(
            'drift: %d difference(s) across %d compared operation(s).%s',
            \count($receipt->differences),
            $receipt->operationsCompared,
            PHP_EOL,
        );
        foreach ($receipt->differences as $difference) {
            echo \sprintf('  [%s] %s: %s%s', $difference->kind, $difference->pointer, $difference->message, PHP_EOL);
        }
    }
}
