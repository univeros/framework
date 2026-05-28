<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * Presentation partial (excluded from static analysis). Shares $data with its
 * parent template: expects $data['panels'] and $data['active'] (active panel id,
 * '' on the overview).
 */

use Altair\Observatory\View\IconSet;
use Altair\Observatory\View\TemplateRenderer;

/** @var array<string, mixed> $data */
$navPanels = \is_array($data['panels'] ?? null) ? $data['panels'] : [];
$active = (string) ($data['active'] ?? '');
$esc = static fn(int|float|string|bool|null $v): string => TemplateRenderer::e($v);
?>
<aside class="o-sidebar">
    <div class="o-brand"><span class="o-brand-mark"><?= IconSet::svg('overview', 'o-brand-mark') ?></span> Observatory</div>
    <a class="o-nav-item<?= $active === '' ? ' is-active' : '' ?>" href="?"><?= IconSet::svg('overview') ?><span>Overview</span></a>
    <div class="o-nav-group">Monitors</div>
    <?php foreach ($navPanels as $id => $navItem): ?>
        <a class="o-nav-item<?= (string) $id === $active ? ' is-active' : '' ?>" href="?panel=<?= $esc((string) $id) ?>">
            <?= IconSet::svg((string) ($navItem['icon'] ?? '')) ?>
            <span><?= $esc((string) ($navItem['label'] ?? $id)) ?></span>
        </a>
    <?php endforeach; ?>
</aside>
