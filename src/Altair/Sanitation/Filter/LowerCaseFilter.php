<?php
namespace Altair\Sanitation\Filter;

class LowerCaseFilter extends AbstractFilter
{
    /**
     * @var bool
     */
    protected $firstOnly;

    /**
     * LowerCaseFilter constructor.
     *
     * @param bool $firstOnly
     */
    public function __construct(bool $firstOnly = false)
    {
        $this->firstOnly = $firstOnly;
    }

    /**
     * @param mixed $value
     *
     * @return null|string
     */
    public function parse($value)
    {
        if (!is_scalar($value)) {
            return null;
        }

        return is_scalar($value)
            ? ($this->firstOnly ? $this->getFirstToLower($value) : strtolower($value))
            : null;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getFirstToLower(string $value): string
    {
        $length = mb_strlen($value);
        if ($length == 0) {
            return '';
        }
        if ($length > 1) {
            $head = mb_substr($value, 0, 1);
            $tail = mb_substr($value, 1);

            return strtolower($head) . $tail;
        }

        return strtolower($value);
    }


}
