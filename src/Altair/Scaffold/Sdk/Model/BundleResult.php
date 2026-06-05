<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

/**
 * Outcome of {@see RefBundler::bundle()}: the document with every resolvable
 * external `$ref` inlined into `components/schemas` (and rewritten to an
 * internal ref), plus one warning per ref that could not be safely bundled.
 */
final readonly class BundleResult
{
    /**
     * @param array<string, mixed> $document
     * @param list<string>         $warnings
     */
    public function __construct(
        public array $document,
        public array $warnings,
    ) {}
}
