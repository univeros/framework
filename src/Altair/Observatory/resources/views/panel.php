<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * Presentation template (excluded from static analysis). Receives $data:
 *   $data['panels']  : full dashboard map (for the sidebar)
 *   $data['active']  : the focused panel id
 *   $data['streamUrl']: optional SSE URL for the activity live-tail ('' = off)
 */

use Altair\Observatory\View\TemplateRenderer;

/** @var array<string, mixed> $data */
$panels = \is_array($data['panels'] ?? null) ? $data['panels'] : [];
$active = (string) ($data['active'] ?? '');
$streamUrl = (string) ($data['streamUrl'] ?? '');
$css = (string) @file_get_contents(__DIR__ . '/../assets/observatory.css');
$e = static fn(int|float|string|bool|null $v): string => TemplateRenderer::e($v);

$panel = \is_array($panels[$active] ?? null) ? $panels[$active] : null;
$snapshot = \is_array($panel['snapshot'] ?? null) ? $panel['snapshot'] : [];
$status = (string) ($snapshot['status'] ?? 'unknown');
$metrics = \is_array($snapshot['metrics'] ?? null) ? $snapshot['metrics'] : [];
$items = \is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];

$columns = [];
foreach ($items as $row) {
    if (\is_array($row)) {
        foreach (array_keys($row) as $key) {
            $columns[(string) $key] = true;
        }
    }
}
$columns = array_keys($columns);
$liveTail = $active === 'events' && $streamUrl !== '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Observatory — <?= $e((string) ($panel['label'] ?? $active)) ?></title>
    <style><?= $css ?></style>
</head>
<body>
<div class="o-app">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="o-main">
        <header class="o-topbar">
            <a class="o-chip" href="?">&larr; Overview</a>
            <h1><?= $e((string) ($panel['label'] ?? $active)) ?></h1>
            <?php if ($panel !== null): ?>
                <span class="o-badge" data-status="<?= $e($status) ?>"><span class="o-dot"></span> <?= $e(strtoupper($status)) ?></span>
            <?php endif; ?>
            <span class="o-spacer"></span>
            <?php if ($liveTail): ?><span class="o-chip o-live"><span class="o-dot"></span> LIVE</span><?php endif; ?>
        </header>

        <div class="o-content">
            <?php if ($panel === null): ?>
                <div class="o-empty"><h2>Panel &ldquo;<?= $e($active) ?>&rdquo; not found</h2></div>
            <?php else: ?>
                <div class="o-headline o-tabular" style="margin-bottom:6px"><?= $e((string) ($snapshot['headline'] ?? '')) ?></div>
                <?php if ($metrics !== []): ?>
                    <div class="o-metrics" style="margin-bottom:18px">
                        <?php foreach ($metrics as $key => $value): ?>
                            <span class="o-metric"><?= $e((string) $key) ?> <b class="o-tabular"><?= $e(\is_scalar($value) ? $value : '') ?></b></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($columns !== []): ?>
                    <input class="o-filter" type="search" placeholder="Filter rows&hellip;" aria-label="Filter rows"
                           oninput="(function(q){document.querySelectorAll('#o-rows tr').forEach(function(r){r.style.display=r.textContent.toLowerCase().indexOf(q.toLowerCase())>-1?'':'none';});})(this.value)">
                    <table class="o-table">
                        <thead><tr><?php foreach ($columns as $column): ?><th><?= $e($column) ?></th><?php endforeach; ?></tr></thead>
                        <tbody id="o-rows">
                        <?php foreach ($items as $row): if (!\is_array($row)) {
                            continue;
                        } ?>
                            <tr><?php foreach ($columns as $column):
                                $value = $row[$column] ?? '';
                                $numeric = \in_array($column, ['id', 'duration_ms', 'timestamp'], true);
                                ?><td<?= $numeric ? ' class="o-num"' : '' ?>><?= $e(\is_scalar($value) ? $value : '') ?></td><?php endforeach; ?></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="o-empty">No detail rows.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <footer class="o-footer">
            <span class="o-spacer"></span>
            <span>Powered by the <a href="https://univeros.io" target="_blank" rel="noopener">Altair Framework</a></span>
        </footer>
    </main>
</div>
<?php if ($liveTail): ?>
<script>
(function () {
    var columns = <?= json_encode($columns, JSON_THROW_ON_ERROR) ?>;
    var tbody = document.getElementById('o-rows');
    if (!tbody || !window.EventSource) { return; }
    var source = new EventSource(<?= json_encode($streamUrl, JSON_THROW_ON_ERROR) ?>);
    source.addEventListener('activity', function (event) {
        var data;
        try { data = JSON.parse(event.data); } catch (e) { return; }
        var row = document.createElement('tr');
        columns.forEach(function (column) {
            var cell = document.createElement('td');
            cell.textContent = data[column] != null ? String(data[column]) : '';
            row.appendChild(cell);
        });
        tbody.insertBefore(row, tbody.firstChild);
    });
})();
</script>
<?php endif; ?>
</body>
</html>
