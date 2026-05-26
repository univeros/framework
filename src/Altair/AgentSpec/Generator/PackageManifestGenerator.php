<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\AgentSpec\Generator;

use Altair\AgentSpec\Model\PackageDescriptor;
use Altair\AgentSpec\Model\PackageManifest;
use Altair\AgentSpec\Reflection\AttributeScanner;
use Altair\AgentSpec\Reflection\ConcreteClassScanner;
use Altair\AgentSpec\Reflection\ContractScanner;
use Altair\AgentSpec\Reflection\SidecarReader;
use Altair\AgentSpec\Reflection\TestFixtureScanner;

/**
 * Composes the reflection scanners into a single PackageManifest for one
 * sub-package descriptor. Pure — same input always yields the same output.
 */
class PackageManifestGenerator
{
    public function __construct(
        private readonly ContractScanner $contracts = new ContractScanner(),
        private readonly ConcreteClassScanner $classes = new ConcreteClassScanner(),
        private readonly AttributeScanner $attributes = new AttributeScanner(),
        private readonly TestFixtureScanner $tests = new TestFixtureScanner(),
        private readonly SidecarReader $sidecar = new SidecarReader(),
    ) {}

    public function generate(PackageDescriptor $package): PackageManifest
    {
        $purpose = $this->sidecar->readPurpose($package) ?? $package->description;

        return new PackageManifest(
            packageName: $package->packageName,
            rootNamespace: $package->rootNamespace,
            purpose: $purpose,
            contracts: $this->contracts->scan($package),
            concreteClasses: $this->classes->scan($package),
            attributeConventions: $this->attributes->scan($package),
            testReferences: $this->tests->scan($package),
            relatedPackages: $package->requiredPackages,
            commonPatterns: $this->sidecar->readPatterns($package),
            stabilityNote: $this->sidecar->readStability($package) ?? '',
        );
    }
}
