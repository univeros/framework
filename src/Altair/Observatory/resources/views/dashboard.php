<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * Presentation template (excluded from static analysis). Receives $data:
 *   $data['panels']: array<string, array{label, icon, snapshot: array{status, headline, metrics, items}}>
 */

use Altair\Observatory\View\IconSet;
use Altair\Observatory\View\TemplateRenderer;

/** @var array<string, mixed> $data */
$panels = \is_array($data['panels'] ?? null) ? $data['panels'] : [];
$css = (string) @file_get_contents(__DIR__ . '/../assets/observatory.css');
$e = static fn(int|float|string|bool|null $v): string => TemplateRenderer::e($v);
$icon = static fn(string $k, string $c = ''): string => IconSet::svg($k, $c);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Observatory</title>
    <style><?= $css ?></style>
</head>
<body>
<div class="o-app">
    <aside class="o-sidebar">
        <div class="o-brand"><span class="o-brand-mark"><?= $icon('overview', 'o-brand-mark') ?></span> Observatory</div>
        <div class="o-nav-group">Monitors</div>
        <?php foreach ($panels as $id => $panel): ?>
            <a class="o-nav-item" href="#panel-<?= $e((string) $id) ?>">
                <?= $icon((string) ($panel['icon'] ?? '')) ?>
                <span><?= $e((string) ($panel['label'] ?? $id)) ?></span>
            </a>
        <?php endforeach; ?>
    </aside>

    <main class="o-main">
        <header class="o-topbar">
            <h1>Overview</h1>
            <span class="o-spacer"></span>
            <span class="o-chip o-live"><span class="o-dot"></span> LIVE</span>
            <button class="o-chip" type="button" onclick="document.documentElement.dataset.theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'">Theme</button>
        </header>

        <div class="o-content">
            <div class="o-grid">
                <?php foreach ($panels as $id => $panel):
                    $snapshot = \is_array($panel['snapshot'] ?? null) ? $panel['snapshot'] : [];
                    $status = (string) ($snapshot['status'] ?? 'unknown');
                    $metrics = \is_array($snapshot['metrics'] ?? null) ? $snapshot['metrics'] : [];
                    ?>
                    <section class="o-card" data-status="<?= $e($status) ?>" id="panel-<?= $e((string) $id) ?>">
                        <div class="o-card-head">
                            <span class="o-card-title"><?= $icon((string) ($panel['icon'] ?? '')) ?> <?= $e((string) ($panel['label'] ?? $id)) ?></span>
                            <span class="o-spacer"></span>
                            <span class="o-badge" data-status="<?= $e($status) ?>"><span class="o-dot"></span> <?= $e(strtoupper($status)) ?></span>
                        </div>
                        <div class="o-headline o-tabular"><?= $e((string) ($snapshot['headline'] ?? '')) ?></div>
                        <?php if ($metrics !== []): ?>
                            <div class="o-metrics">
                                <?php foreach ($metrics as $key => $value): ?>
                                    <span class="o-metric"><?= $e((string) $key) ?> <b class="o-tabular"><?= $e(\is_scalar($value) ? $value : '') ?></b></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="o-card-foot"><a href="#panel-<?= $e((string) $id) ?>">view &rarr;</a></div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>
</body>
</html>
