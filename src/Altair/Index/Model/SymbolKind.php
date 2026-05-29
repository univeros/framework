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
 * The category of a declared symbol recorded in the index.
 *
 * Case names follow the php-parser convention of a trailing underscore on
 * reserved words; the backing string is the lowercase token stored in the
 * `symbols.kind` column.
 */
enum SymbolKind: string
{
    case Class_ = 'class';
    case Interface_ = 'interface';
    case Trait_ = 'trait';
    case Enum_ = 'enum';
    case Method = 'method';
    case Property = 'property';
    case Constant = 'constant';

    public function isClassLike(): bool
    {
        return match ($this) {
            self::Class_, self::Interface_, self::Trait_, self::Enum_ => true,
            default => false,
        };
    }
}
