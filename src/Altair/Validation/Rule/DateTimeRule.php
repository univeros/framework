<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use DateTime;
use Override;

class DateTimeRule extends AbstractRule
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function assert($value): bool
    {
        if ($value instanceof DateTime) {
            return (bool) $value;
        }

        if (!\is_scalar($value) || trim($value) === '') {
            return false;
        }

        $datetime = date_create($value);

        if ($datetime === false) {
            return false;
        }

        $errors = DateTime::getLastErrors();

        if ($errors !== false && !empty($errors['warnings'])) {
            return false;
        }

        return (bool) $datetime;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildErrorMessage($value): string
    {
        return \sprintf('"%s" is not a valid date time value.', $value);
    }
}
