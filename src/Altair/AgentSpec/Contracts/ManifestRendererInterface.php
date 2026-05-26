<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Contracts;

use Altair\AgentSpec\Model\PackageManifest;

interface ManifestRendererInterface
{
    /**
     * Render the given manifest to its target format (Markdown, JSON, etc.).
     * Output MUST be deterministic — calling render twice with the same input
     * yields byte-identical strings.
     */
    public function render(PackageManifest $manifest): string;
}
