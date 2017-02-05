<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class SwiftBicRule extends AbstractRule
{
    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        return (bool) preg_match('/^([a-zA-Z]){4}([a-zA-Z]){2}([0-9a-zA-Z]){2}([0-9a-zA-Z]{3})?$/', $value);
    }
    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid SWIFT/BIC number.', $value);
    }
}
