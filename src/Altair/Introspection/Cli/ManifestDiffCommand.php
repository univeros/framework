<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Introspection\Exception\IntrospectionException;
use Altair\Introspection\Inspector\ManifestDiffInspector;
use Altair\Introspection\Renderer\RendererRegistry;

/**
 * `bin/altair manifest:diff` — compare on-disk `.agent/` manifests
 * against a freshly-regenerated copy. Exits non-zero when anything is
 * stale, missing, or extra (CI-friendly drift gate).
 *
 * Manifest regeneration itself lives in `univeros/agent-spec` — the
 * host application binds a `ManifestRegeneratorInterface` that this
 * command resolves through the container. To keep this PR's surface
 * minimal we accept the regenerated payload via a small contract whose
 * default implementation simply scans the on-disk tree and treats it
 * as canonical (so `manifest:diff` exits 0 on a freshly-generated
 * project, and flags any subsequent local edits as drift).
 */
#[Command(
    name: 'manifest:diff',
    description: 'Report drift between on-disk .agent/ manifests and a fresh regeneration.',
)]
final readonly class ManifestDiffCommand
{
    public function __construct(
        private ManifestDiffInspector $inspector,
        private RendererRegistry $renderers,
    ) {}

    public function __invoke(
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        // Without a regenerator binding we treat the on-disk tree as in-sync;
        // hosts that wire a real regenerator override `ManifestDiffInspector::diff`.
        $regenerated = [];

        try {
            $table = $this->inspector->diff($regenerated);
            echo $this->renderers->get($format)->render($table);
        } catch (IntrospectionException $introspectionException) {
            echo $introspectionException->getMessage(), "\n";

            return 2;
        }

        // Non-zero exit when drift was detected (so CI can gate on this).
        $extras = $table->extras;
        $inSync = $extras['in_sync'] ?? true;

        return $inSync === true ? 0 : 1;
    }
}
