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
 * How a symbol is referenced at a given code (or spec) location.
 *
 * The backing string is the token stored in the `usages.usage_kind` column and
 * surfaced verbatim in JSON output, so it is part of the package's contract.
 */
enum UsageKind: string
{
    case New_ = 'new';
    case Extends_ = 'extends';
    case Implements_ = 'implements';
    case TypeHint = 'type_hint';
    case Call = 'call';
    case PropertyRead = 'property_read';
    case PropertyWrite = 'property_write';
    case Attribute = 'attribute';
    case ClassConstant = 'class_constant';
    case SpecEndpoint = 'spec_endpoint';
    case SpecEntity = 'spec_entity';
    case RouteMiddleware = 'route_middleware';

    /**
     * Usages discovered from the framework's higher-level constructs rather than
     * from raw PHP source.
     */
    public function isFrameworkAware(): bool
    {
        return match ($this) {
            self::SpecEndpoint, self::SpecEntity, self::RouteMiddleware => true,
            default => false,
        };
    }
}
