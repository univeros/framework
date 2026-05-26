<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Reflection;

use Altair\AgentSpec\Contracts\PhpFileFinderInterface;
use Altair\AgentSpec\Model\PackageDescriptor;
use Altair\AgentSpec\Model\TestReference;

/**
 * Lists every *Test.php file under the package's mirrored tests directory.
 */
class TestFixtureScanner
{
    public function __construct(
        private readonly PhpFileFinderInterface $finder = new PhpFileFinder(),
    ) {}

    /**
     * @return list<TestReference>
     */
    public function scan(PackageDescriptor $package): array
    {
        if ($package->testsPath === null || $package->relativeTestsPath === null) {
            return [];
        }

        $references = [];
        foreach ($this->finder->find($package->testsPath) as $file) {
            if (!str_ends_with($file, 'Test.php')) {
                continue;
            }

            $relative = $package->relativeTestsPath . '/' . substr($file, \strlen($package->testsPath) + 1);
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

            $references[] = new TestReference(
                relativePath: $relative,
                shortName: basename($file, '.php'),
            );
        }

        usort($references, static fn(TestReference $a, TestReference $b): int => strcmp($a->relativePath, $b->relativePath));

        return $references;
    }
}
