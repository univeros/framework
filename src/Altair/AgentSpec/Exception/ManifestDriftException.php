<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Exception;

/**
 * Thrown by --check mode when a regenerated manifest does not match what is on disk.
 */
class ManifestDriftException extends AgentSpecException {}
