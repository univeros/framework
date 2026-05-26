<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/tests/bootstrap.php',
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
