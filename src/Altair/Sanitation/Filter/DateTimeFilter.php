<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

use DateTime;

class DateTimeFilter extends AbstractFilter
{
    protected $format;

    /**
     * DateTimeFilter constructor.
     *
     * @param string $format
     */
    public function __construct(string $format = null)
    {
        $this->format = $format?? 'Y-m-d H:i:s';
    }

    /**
     * @inheritDoc
     */
    public function parse($value)
    {
        $value = $this->buildDateTime($value);

        return (null !== $value)
            ? $value->format($this->format)
            : $value;
    }

    /**
     * Creates a new datetime based on the value, otherwise returns null.
     *
     * @param $value
     *
     * @return DateTime|null
     */
    protected function buildDateTime($value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }
        if (!is_scalar($value)) {
            return null;
        }
        if (trim($value) === '') {
            return null;
        }
        $datetime = date_create($value);

        $errors = DateTime::getLastErrors();

        if ($datetime === false || $errors['warnings']) {
            return null;
        }

        return $datetime;
    }
}
