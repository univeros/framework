<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;
use DateTime;

class DateTimeRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if ($value instanceof DateTime) {
            return (bool)$value;
        }
        if (!is_scalar($value) || trim($value) === '') {
            return false;
        }

        $datetime = date_create($value);

        $errors = DateTime::getLastErrors();

        // errors show as warnings
        return $errors['warnings'] ? false : (bool)$datetime;
    }
    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid date time value.', $value);
    }
}
