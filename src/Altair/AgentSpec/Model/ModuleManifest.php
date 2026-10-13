<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Model;

use Altair\AgentSpec\Exception\AgentSpecException;

/**
 * A manifest section contributed by a host module, written to
 * `modules/<name>.md` under the output root (`modules/univeros/polaris.md`).
 */
final readonly class ModuleManifest
{
    /**
     * `module` or `vendor/module`, lowercase; keeps the file path inside `modules/`.
     */
    private const string NAME_PATTERN = '#^[a-z0-9]([_.-]?[a-z0-9]+)*(/[a-z0-9]([_.-]?[a-z0-9]+)*)?\z#';

    public string $contents;

    public function __construct(
        public string $name,
        public string $description,
        string $contents,
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new AgentSpecException(\sprintf("Module name '%s' must be lowercase 'module' or 'vendor/module' to name a manifest file.", $name));
        }

        $this->contents = rtrim($contents, "\n") . "\n";
    }

    public function path(): string
    {
        return 'modules/' . $this->name . '.md';
    }
}
