<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

use Altair\MigrationIntelligence\Intent\IntentInterface;

/**
 * A single read-only check that inspects one intent against the live database
 * and yields zero or more findings. Checks must not mutate anything.
 */
interface SafetyCheckInterface
{
    /**
     * @return list<SafetyFinding>
     */
    public function check(IntentInterface $intent, RowCounter $rows): array;
}
