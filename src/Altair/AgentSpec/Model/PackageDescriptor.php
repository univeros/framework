<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Model;

/**
 * Locates one framework sub-package on disk plus its meta from composer.json.
 * Used as the input to PackageManifestGenerator.
 */
final readonly class PackageDescriptor
{
    /**
     * @param list<string> $requiredPackages Composer require list (raw names).
     */
    public function __construct(
        public string $packageName,
        public string $description,
        public string $rootNamespace,
        public string $sourcePath,
        public string $relativeSourcePath,
        public ?string $testsPath,
        public ?string $relativeTestsPath,
        public string $manifestSlug,
        public array $requiredPackages,
    ) {}
}
