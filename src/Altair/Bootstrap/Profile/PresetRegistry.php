<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Profile;

use Altair\Bootstrap\Contracts\PresetInterface;
use Altair\Bootstrap\Exception\BootstrapException;

/**
 * Resolves a preset by name. The default set is minimal / standard / full.
 */
final class PresetRegistry
{
    /**
     * @var array<string, PresetInterface>
     */
    private array $presets = [];

    public function __construct(PresetInterface ...$presets)
    {
        $defaults = $presets === [] ? [new MinimalPreset(), new StandardPreset(), new FullPreset()] : $presets;
        foreach ($defaults as $preset) {
            $this->presets[$preset->name()] = $preset;
        }
    }

    public function get(string $name): PresetInterface
    {
        return $this->presets[$name]
            ?? throw new BootstrapException(\sprintf(
                "Unknown preset '%s'. Available: %s.",
                $name,
                implode(', ', $this->names()),
            ));
    }

    public function has(string $name): bool
    {
        return isset($this->presets[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->presets);
    }
}
