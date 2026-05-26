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
 * The structured, deterministic description of one framework package.
 * Renderers transform this into Markdown (and optionally JSON).
 */
final readonly class PackageManifest
{
    /**
     * @param list<ContractEntry>       $contracts            Sorted by short name.
     * @param list<ClassEntry>          $concreteClasses      Sorted by short name.
     * @param list<AttributeConvention> $attributeConventions Sorted by value, then constant name.
     * @param list<TestReference>       $testReferences       Sorted by relative path.
     * @param list<string>              $relatedPackages      Sorted alphabetically. Composer package names.
     * @param list<string>              $commonPatterns       Free-form markdown fragments (verbatim, ordered as supplied).
     */
    public function __construct(
        public string $packageName,
        public string $rootNamespace,
        public string $purpose,
        public array $contracts,
        public array $concreteClasses,
        public array $attributeConventions,
        public array $testReferences,
        public array $relatedPackages,
        public array $commonPatterns,
        public string $stabilityNote,
    ) {}
}
