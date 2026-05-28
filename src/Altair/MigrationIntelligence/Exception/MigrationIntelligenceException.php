<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Exception;

use RuntimeException;

/**
 * Raised for caller-facing failures: an unknown driver/format, a spec without a
 * `persistence:` block, or an unresolvable diff source. Safety checks never
 * throw — an unreachable database degrades to a skipped report.
 */
final class MigrationIntelligenceException extends RuntimeException {}
