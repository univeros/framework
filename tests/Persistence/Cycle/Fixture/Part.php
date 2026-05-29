<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Persistence\Cycle\Fixture;

/**
 * Child entity for the has-many relation used in read-model relation tests.
 */
final class Part
{
    public function __construct(
        public int $id,
        public int $widget_id,
        public string $label,
    ) {}
}
