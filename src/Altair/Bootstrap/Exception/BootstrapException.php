<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Exception;

use RuntimeException;

/**
 * Raised when a project cannot be bootstrapped — an unknown preset, a target
 * directory that already has files, or a missing skeleton template.
 */
final class BootstrapException extends RuntimeException {}
