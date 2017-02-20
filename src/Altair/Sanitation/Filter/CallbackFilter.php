<?php
namespace Altair\Sanitation\Filter;

class CallbackFilter extends AbstractFilter
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
    public function parse($value)
    {
        return call_user_func($this->callable, $value);
    }


}
