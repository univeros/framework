<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Contracts;

use Altair\AgentSpec\Model\PackageDescriptor;

interface PackageScannerInterface
{
    /**
     * Discovers framework sub-packages under the given source root.
     *
     * @return list<PackageDescriptor> Sorted alphabetically by package name.
     */
    public function scan(string $sourceRoot, string $monorepoRoot, ?string $testsRoot): array;
}
