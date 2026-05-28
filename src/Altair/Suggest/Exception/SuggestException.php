<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Exception;

use RuntimeException;

/**
 * Raised for caller-facing failures: an unknown `--format`, an unknown
 * `--severity`. Internal analysis never throws — a rule that cannot reason
 * about the snapshot simply yields no suggestions.
 */
final class SuggestException extends RuntimeException {}
