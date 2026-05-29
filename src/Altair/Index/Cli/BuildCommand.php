<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Index\Builder\BuildResult;
use Altair\Index\Support\Json;
use Altair\Index\Support\ResolvesProjectIndex;

/**
 * `bin/altair index:build` — (re)build the symbol-usage index.
 *
 * A full build by default; `--incremental` re-walks only files whose content
 * changed since the last build and drops files that disappeared.
 */
#[Command(
    name: 'index:build',
    description: 'Build the symbol-usage index (use --incremental for a fast partial rebuild).',
)]
final readonly class BuildCommand
{
    use ResolvesProjectIndex;

    public function __invoke(
        #[Option(description: 'Re-walk only changed files instead of a full rebuild.')]
        bool $incremental = false,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $result = $this->index()->builder()->build($incremental);

        echo $format === 'json' ? Json::encode($result->toArray()) : $this->human($result);

        return 0;
    }

    private function human(BuildResult $result): string
    {
        return \sprintf(
            "Index %s: %d symbols, %d usages across %d files (indexed %d, skipped %d, removed %d) in %dms.\n",
            $result->incremental ? 'updated' : 'built',
            $result->symbolCount,
            $result->usageCount,
            $result->filesScanned,
            $result->filesIndexed,
            $result->filesSkipped,
            $result->filesRemoved,
            $result->durationMs,
        );
    }
}
