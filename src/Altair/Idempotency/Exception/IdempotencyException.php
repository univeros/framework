<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Idempotency\Exception;

use RuntimeException;

/**
 * Raised by an {@see \Altair\Idempotency\Contracts\IdempotencyStoreInterface}
 * adapter when an irrecoverable storage failure occurs — connection lost,
 * backend missing, etc. Recoverable conditions (key already claimed,
 * payload mismatch) are signalled by return value, not exception.
 */
class IdempotencyException extends RuntimeException {}
