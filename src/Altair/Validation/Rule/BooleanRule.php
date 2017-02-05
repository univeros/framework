<?php
namespace Altair\Validation\Rule;

class BooleanRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        return is_bool(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE));
    }
    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid boolean value.', $value);
    }
}
