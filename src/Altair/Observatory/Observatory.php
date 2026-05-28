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
use Altair\Observatory\Security\AccessGuardInterface;

/**
 * The Observatory facade: gate access, expose the registered panels, and
 * project the whole dashboard as plain data.
 *
 * The {@see dashboard()} output is render-agnostic, so it backs both the
 * server-rendered web UI and any JSON endpoint without duplication.
 */
final readonly class Observatory
{
    public function __construct(
        private PanelRegistry $panels,
        private AccessGuardInterface $guard,
    ) {}

    public function isAccessible(): bool
    {
        return $this->guard->allows();
    }

    /**
     * @return list<PanelInterface>
     */
    public function panels(): array
    {
        return $this->panels->all();
    }

    /**
     * @return array<string, array{label: string, icon: string, snapshot: array{status: string, headline: string, metrics: array<string, scalar|null>, items: list<array<string, scalar|null>>}}>
     */
    public function dashboard(): array
    {
        $dashboard = [];

        foreach ($this->panels->all() as $panel) {
            $dashboard[$panel->id()] = [
                'label' => $panel->label(),
                'icon' => $panel->icon(),
                'snapshot' => $panel->snapshot()->toArray(),
            ];
        }

        return $dashboard;
    }
}
