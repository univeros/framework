<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Doctor;

use Altair\Doctor\Contracts\CheckInterface;

/**
 * The ordered set of checks a doctor run executes.
 *
 * Order matters: checks run top-to-bottom, and `dependsOn()` references
 * resolve against checks already executed. Hosts extend the registry by
 * `add()`-ing their own checks (typically via a Container `prepare` hook).
 */
final class CheckRegistry
{
    /**
     * @var list<CheckInterface>
     */
    private array $checks = [];

    /**
     * @param list<CheckInterface> $checks
     */
    public function __construct(array $checks = [])
    {
        foreach ($checks as $check) {
            $this->add($check);
        }
    }

    public function add(CheckInterface $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @return list<CheckInterface>
     */
    public function all(): array
    {
        return $this->checks;
    }
}
