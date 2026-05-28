<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\View;

/**
 * A tiny set of self-contained, consistent line icons (1.5 stroke, 24-grid).
 *
 * Authored inline rather than pulling an icon dependency so Observatory stays
 * zero-build. Panels reference icons by key via {@see PanelInterface::icon()};
 * unknown keys fall back to a neutral glyph.
 */
final class IconSet
{
    /**
     * @var array<string, string> inner SVG markup (paths) per icon key
     */
    private const array ICONS = [
        'overview' => '<line x1="3" y1="21" x2="21" y2="21"/><rect x="5" y="11" width="3" height="8" rx="0.5"/><rect x="11" y="6" width="3" height="13" rx="0.5"/><rect x="17" y="14" width="3" height="5" rx="0.5"/>',
        'server' => '<rect x="3" y="4" width="18" height="7" rx="1.5"/><rect x="3" y="13" width="18" height="7" rx="1.5"/><circle cx="6.5" cy="7.5" r="0.9"/><circle cx="6.5" cy="16.5" r="0.9"/>',
        'health' => '<polyline points="3,12 7.5,12 10,5 14,19 16.5,12 21,12"/>',
        'events' => '<circle cx="5" cy="19" r="1.6"/><path d="M5 13a6 6 0 0 1 6 6"/><path d="M5 8a11 11 0 0 1 11 11"/>',
        'queues' => '<circle cx="4" cy="6" r="1.1"/><circle cx="4" cy="12" r="1.1"/><circle cx="4" cy="18" r="1.1"/><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/>',
        'routes' => '<path d="M12 2 21 7 21 17 12 22 3 17 3 7Z"/><path d="M3 7 12 12 21 7"/><line x1="12" y1="12" x2="12" y2="22"/>',
        'container' => '<rect x="4" y="4" width="16" height="16" rx="2"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="9" y1="9" x2="9" y2="20"/>',
        'config' => '<line x1="4" y1="8" x2="20" y2="8"/><circle cx="9" cy="8" r="2.2"/><line x1="4" y1="16" x2="20" y2="16"/><circle cx="15" cy="16" r="2.2"/>',
        'migrations' => '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/><path d="M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
    ];

    /**
     * Panel icon keys mapped onto the canonical glyphs above.
     *
     * @var array<string, string>
     */
    private const array ALIASES = [
        'heart-pulse' => 'health',
        'shield-check' => 'health',
        'rss' => 'events',
        'signal' => 'events',
        'queue-list' => 'queues',
        'map' => 'routes',
        'cube' => 'container',
        'adjustments' => 'config',
        'circle-stack' => 'migrations',
        'chart-bar' => 'overview',
    ];

    public static function svg(string $key, string $class = ''): string
    {
        $key = self::ALIASES[$key] ?? $key;
        $inner = self::ICONS[$key] ?? '<circle cx="12" cy="12" r="8"/>';
        $classAttr = $class === '' ? '' : \sprintf(' class="%s"', htmlspecialchars($class, ENT_QUOTES));

        return \sprintf(
            '<svg%s viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">%s</svg>',
            $classAttr,
            $inner,
        );
    }
}
