<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory;

use Altair\Observatory\Contracts\PanelInterface;

/**
 * Collects the panels exposed by Observatory, keyed by their id.
 *
 * Registering a panel with an existing id replaces it, so hosts can override a
 * built-in panel by registering their own with the same id after configuration.
 */
final class PanelRegistry
{
    /**
     * @var array<string, PanelInterface>
     */
    private array $panels = [];

    /**
     * @param list<PanelInterface> $panels
     */
    public function __construct(array $panels = [])
    {
        foreach ($panels as $panel) {
            $this->register($panel);
        }
    }

    public function register(PanelInterface $panel): void
    {
        $this->panels[$panel->id()] = $panel;
    }

    /**
     * @return list<PanelInterface>
     */
    public function all(): array
    {
        return array_values($this->panels);
    }

    public function get(string $id): ?PanelInterface
    {
        return $this->panels[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->panels[$id]);
    }
}
