<?php
namespace Altair\Validation\Rule;

class AlphaNumRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return (bool)preg_match('/^[\p{L}\p{Nd}]+$/u', $value);
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" have invalid alphanumeric character(s)', $value);
    }
}
