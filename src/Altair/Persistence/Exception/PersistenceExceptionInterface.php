<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Exception;

use Throwable;

/**
 * Marker interface so callers can `catch (PersistenceExceptionInterface $e)`
 * regardless of the concrete exception type emitted.
 */
interface PersistenceExceptionInterface extends Throwable {}
