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

class TitleCaseFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function parse($value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        return ucwords($value);
    }
}
