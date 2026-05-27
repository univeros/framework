<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Contracts;

/**
 * Marker for message handlers. Implementations expose an `__invoke`
 * method whose single argument is the message class declared by the
 * #[AsHandler(...)] attribute.
 *
 * Implementing this interface is optional — the discoverer scans for
 * #[AsHandler], not for an interface — but it documents intent and
 * lets static analyzers narrow handler types.
 */
interface HandlerInterface {}
