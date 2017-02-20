<?php
namespace Altair\Sanitation\Filter;


class MinStrLengthFilter extends AbstractFilter
{
    /**
     * @var int
     */
    protected $min;
    /**
     * @var string
     */
    protected $pad;
    /**
     * @var int
     */
    protected $direction;

    /**
     * MaxStrLengthFilter constructor.
     *
     * @param int $min
     * @param string $pad
     * @param int $direction
     */
    public function __construct(int $min, string $pad = null, int $direction = STR_PAD_RIGHT)
    {
        $this->min = $min;
        $this->pad = $pad?? ' ';
        $this->direction = $direction;
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_string($value)) {
            return null;
        }

        if (mb_strlen($value) < $this->min) {
            return str_pad($value, $this->min, $this->pad, $this->direction);
        }

        return $value;
    }

}
