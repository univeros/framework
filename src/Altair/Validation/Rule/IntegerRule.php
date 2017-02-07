<?php
namespace Altair\Validation\Rule;

class IntegerRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        return is_int($value) || (is_numeric($value) && $value == (int)$value);
    }
    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid integer value.', $value);
    }
}
