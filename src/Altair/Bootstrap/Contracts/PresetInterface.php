<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Bootstrap\Contracts;

/**
 * A named bundle of bootstrap choices. A preset answers the two questions the
 * interactive `new` command would otherwise ask — which ORM and which queue
 * transport — so a project can be generated non-interactively.
 */
interface PresetInterface
{
    public function name(): string;

    /**
     * One of: cycle, doctrine, pdo, none.
     */
    public function orm(): string;

    /**
     * One of: redis, doctrine, sync, none.
     */
    public function queue(): string;
}
