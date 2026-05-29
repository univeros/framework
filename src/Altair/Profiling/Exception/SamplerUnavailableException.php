<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Exception;

use RuntimeException;

/**
 * Thrown when no sampling-profiler backend is loaded: neither `ext-excimer`
 * (the recommended one) nor `ext-xdebug`. The message includes install hints
 * so a CLI user or an MCP agent sees what to do next.
 */
final class SamplerUnavailableException extends RuntimeException
{
    public static function noBackend(): self
    {
        return new self(
            "No sampling-profiler backend is loaded.\n"
            . "Install one of:\n"
            . "  - ext-excimer (preferred — low overhead): https://github.com/wikimedia/mediawiki-php-excimer\n"
            . "  - ext-xdebug (alternative): https://xdebug.org/docs/install\n"
            . 'Verify with: php -m | grep -iE "excimer|xdebug"',
        );
    }
}
