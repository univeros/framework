<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Result;

use Altair\Suggest\Exception\SuggestException;

/**
 * How strongly a suggestion is held.
 *
 * `warning` is reserved for high-confidence "this does nothing" findings
 * (a wired event with no listeners); `info` is advisory ("consider
 * splitting this", "this looks unreferenced — verify"). `rank()` drives the
 * `--severity` floor filter and the report's exit code.
 */
enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';

    public function rank(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Warning => 2,
        };
    }

    /**
     * Resolve the `--severity` flag. Unknown values are a caller error.
     */
    public static function fromName(string $name): self
    {
        return self::tryFrom(strtolower(trim($name)))
            ?? throw new SuggestException(\sprintf(
                "Unknown severity '%s'. Available: info, warning.",
                $name,
            ));
    }
}
