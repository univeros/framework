<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Exception;

/**
 * Raised when a tool attempts an operation a guardrail forbids — writing into
 * vendor/.git/composer.json/.env, mutating in readonly mode, or running a
 * database write without the explicit opt-in flag.
 */
final class GuardrailException extends McpException {}
