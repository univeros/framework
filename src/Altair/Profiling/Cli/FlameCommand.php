<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Profiling\Model\ProfileReport;
use Altair\Profiling\Output\FlamegraphRenderer;
use Altair\Profiling\Support\Workspace;

/**
 * `bin/altair profile:flame <id>` — render a stored profile as a flamegraph
 * SVG. With `--out=path.svg` the SVG is written to disk; otherwise it goes to
 * stdout (pipe-friendly: `bin/altair profile:flame <id> > flame.svg`).
 */
#[Command(
    name: 'profile:flame',
    description: 'Render a stored profile as an inline-SVG flamegraph.',
)]
final readonly class FlameCommand
{
    use Workspace;

    public function __invoke(
        #[Argument(description: 'Profile id (as listed by `profile:list`).')]
        string $id,
        #[Option(description: 'Write SVG to this path instead of stdout.')]
        ?string $out = null,
    ): int {
        $report = $this->storage()->load($id);
        if (!$report instanceof ProfileReport) {
            echo "Profile '{$id}' not found.\n";

            return 2;
        }

        $svg = (new FlamegraphRenderer())->render($report);

        if (\is_string($out) && $out !== '') {
            file_put_contents($out, $svg);
            echo \sprintf('Wrote %s%s', $out, PHP_EOL);

            return 0;
        }

        echo $svg;

        return 0;
    }
}
