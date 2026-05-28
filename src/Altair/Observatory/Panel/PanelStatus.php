<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\Panel;

/**
 * The health classification of a panel snapshot, used to colour its card.
 */
enum PanelStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Critical = 'critical';
    case Unknown = 'unknown';
}
