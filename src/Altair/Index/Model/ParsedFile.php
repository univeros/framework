<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Model;

/**
 * The result of walking a single source (or spec) file: every symbol it
 * declares and every usage it makes, plus the content hash used to decide
 * whether an incremental rebuild needs to re-walk the file.
 */
final readonly class ParsedFile
{
    /**
     * @param list<Symbol> $symbols
     * @param list<Usage>  $usages
     */
    public function __construct(
        public string $path,
        public string $hash,
        public array $symbols,
        public array $usages,
    ) {}

    public static function hash(string $code): string
    {
        return hash('xxh128', $code);
    }
}
