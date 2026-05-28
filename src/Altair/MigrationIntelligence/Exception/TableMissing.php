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
 * The desired table does not yet exist in the database, so there is nothing to
 * evolve — the caller should scaffold it instead. Distinct from a usage error
 * so the command can report it and exit 0.
 */
final class TableMissing extends RuntimeException {}
