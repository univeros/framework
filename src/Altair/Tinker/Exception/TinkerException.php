<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tinker\Exception;

use RuntimeException;

/**
 * Raised for caller-facing REPL failures — most notably when PsySH is not
 * installed (it ships only with dev installs / the standalone package).
 */
final class TinkerException extends RuntimeException {}
