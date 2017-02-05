<?php
namespace Validation\Rule;

use Altair\Validation\Rule\AbstractRule;

class CallbackRule extends AbstractRule
{
    /**
     * @var callable
     */
    protected $callable;

    /**
     * CallbackRule constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @inheritdoc
     */
    public function assert($value): bool
    {
        return call_user_func($this->callable, $value);
    }

    /**
     * @inheritdoc
     */
    protected function buildErrorMessage($value): string
    {
        return sprintf('"%s" is not a valid value.', $value);
    }
}
