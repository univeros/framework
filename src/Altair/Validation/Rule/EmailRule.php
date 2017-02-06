<?php
namespace Altair\Validation\Rule;

class EmailRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($input): bool
    {
        // This is a very basic way to test an email. It is highly recommended that you create a custom email validator
        // that makes use of https://github.com/egulias/EmailValidator
        return is_string($input) && filter_var($input, FILTER_VALIDATE_EMAIL);
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid email address.', $value);
    }
}
