<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;
use Rector\Php84\Rector\Param\ExplicitNullableParamTypeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/bootstrap.php',
        // Skeleton template emitted by `bin/altair new` — App-namespaced project
        // code, not framework source.
        __DIR__ . '/src/Altair/Bootstrap/resources/skeleton',
        // Module skeleton emitted by `bin/altair module:new` — VendorModule-namespaced
        // template code, not framework source.
        __DIR__ . '/src/Altair/Bootstrap/resources/module-skeleton',
        // Scope boundary for #93: making params explicitly `?Type` lets Rector
        // collapse `?? new X()` bodies into promoted `= new X()` defaults. That
        // narrows the constructor contract (an explicit `null` arg becomes a
        // TypeError) — a behaviour change that belongs in its own PR, not the
        // nullable-deprecation fix. Remove this skip to adopt it deliberately.
        NewInInitializerRector::class,
        // A child constructor that only calls parent::__construct() is NOT dead when it
        // widens visibility (e.g. Altair\Cookie\Cookie exposes AbstractCookie's protected
        // constructor as public). Removing it breaks `new Cookie()`. The rule can't see the
        // visibility change, so skip it globally.
        \Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingConstructorRector::class,
        // Container test fixtures declare classes used as container-reflection inputs.
        // "Empty" constructors / unused private methods are not actually unused —
        // they're inspected at runtime via Reflection.
        __DIR__ . '/tests/Container/fixtures.php',
        __DIR__ . '/tests/Courier/fixtures.php',
        __DIR__ . '/tests/Sanitation/fixtures.php',
        __DIR__ . '/tests/Validation/fixtures.php',
        // Properties on CacheItem are accessed via Closure::bind reflection from
        // CacheItemPool — Rector's DEAD_CODE/PRIVATIZATION sets cannot see those
        // references and incorrectly strip them.
        \Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class => [
            __DIR__ . '/src/Altair/Cache/CacheItem.php',
        ],
        \Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/src/Altair/Cache/CacheItem.php',
        ],
        // Dead-code removal in tests has false positives: assignments like
        // `$x = makeThing(); isset($x[0]);` look unused to Rector but they're
        // testing side-effecting ArrayAccess semantics — the discarded value
        // matters for the next-line check.
        \Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector::class => [
            __DIR__ . '/tests',
        ],
        \Rector\DeadCode\Rector\Expression\RemoveDeadStmtRector::class => [
            __DIR__ . '/tests',
        ],
        // The fixture class intentionally diverges return type from its parent
        // to test parent:: dispatch. Don't have Rector "fix" it.
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictTypedCallRector::class => [
            __DIR__ . '/tests/Container/fixtures.php',
        ],
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnDirectArrayRector::class => [
            __DIR__ . '/tests/Container/fixtures.php',
        ],
        // CLI discovery fixtures intentionally declare #[Argument]/#[Option]
        // attributes on __invoke parameters. The body returns 0 without using
        // them, but the parameters are the test surface — they're what the
        // AttributeCommandDiscoverer reflects on. Rector sees them as unused.
        \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector::class => [
            __DIR__ . '/tests/Cli/Discovery/fixtures',
        ],
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
        SetList::INSTANCEOF,
    ])
    // PHP 8.4 deprecates implicit-nullable params (`Type $x = null`). The
    // explicit `?Type` form is valid back to 7.1, so this single rule is a
    // forward-compat fix that does not raise the PHP 8.3 floor. The rule is
    // version-bonded to 8.4, so the target is lifted to 8.4 purely to arm it.
    // See issue #93.
    ->withPhpVersion(PhpVersion::PHP_84)
    ->withRules([
        ExplicitNullableParamTypeRector::class,
    ])
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withParallel()
    ->withCache(
        cacheDirectory: __DIR__ . '/.rector-cache',
    );
