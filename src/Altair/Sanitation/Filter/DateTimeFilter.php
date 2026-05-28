<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

use DateTime;
use Override;

class DateTimeFilter extends AbstractFilter
{
    protected string $format;

    /**
     * DateTimeFilter constructor.
     */
    public function __construct(?string $format = null)
    {
        $this->format = $format ?? 'Y-m-d H:i:s';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function parse(mixed $value)
    {
        $value = $this->buildDateTime($value);

        return ($value instanceof DateTime)
            ? $value->format($this->format)
            : $value;
    }

    /**
     * Creates a new datetime based on the value, otherwise returns null.
     */
    protected function buildDateTime(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if (!\is_scalar($value)) {
            return null;
        }

        if (trim($value) === '') {
            return null;
        }

        $datetime = date_create($value);

        if ($datetime === false) {
            return null;
        }

        $errors = DateTime::getLastErrors();

        if ($errors !== false && !empty($errors['warnings'])) {
            return null;
        }

        return $datetime;
    }
}
