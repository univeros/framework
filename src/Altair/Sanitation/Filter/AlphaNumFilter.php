<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

use Override;

class AlphaNumFilter extends AbstractFilter
{
    #[Override]
    public function parse($value): ?string
    {
        return preg_replace('/[^\p{L}\p{Nd}]/u', '', (string) $value);
    }
}
