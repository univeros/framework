<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * Presentation template (excluded from static analysis).
 */

$css = (string) @file_get_contents(__DIR__ . '/../assets/observatory.css');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Observatory — disabled</title>
    <style><?= $css ?></style>
</head>
<body>
<div class="o-empty">
    <h2>Observatory is disabled</h2>
    <p>Set <code class="o-mono">OBSERVATORY_ENABLED=true</code> in a non-production environment to enable the panel.</p>
</div>
</body>
</html>
