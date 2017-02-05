<?php
namespace Altair\Validation\Rule;

class AlphaRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return (bool)preg_match('/^[\p{L}]+$/u', $value);
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" have invalid alphabetic character(s)', $value);
    }
}
