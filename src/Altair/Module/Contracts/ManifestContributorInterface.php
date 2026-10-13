<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Module\Contracts;

/**
 * A module that contributes a section to the host's AI-readable manifests.
 *
 * `bin/altair manifest:generate`, run from the host's own console, writes
 * {@see self::manifest()} to `.agent/modules/<name>.md`, where the name is the
 * module's {@see ModuleInterface::name()} (`univeros/polaris` →
 * `modules/univeros/polaris.md`; lowercase, `module` or `vendor/module`), and
 * lists it in `.agent/MANIFEST.md` with {@see self::manifestDescription()}.
 * Describe what an agent working on the host needs: the routes, auth,
 * permissions, commands the module adds.
 *
 * Both methods must be byte-stable for the same configuration (no timestamps,
 * no unordered maps), so `manifest:generate --check` works in the host's CI.
 */
interface ManifestContributorInterface
{
    /**
     * One line describing the module, shown in the MANIFEST.md index.
     */
    public function manifestDescription(): string;

    /**
     * Markdown body of the module's manifest file.
     */
    public function manifest(): string;
}
