<?php
namespace Altair\Sanitation\Filter;

class TrimFilter extends AbstractFilter
{
    /**
     * @var string
     */
    protected $chars;

    /**
     * TrimFilter constructor.
     *
     * @param string|null $chars
     */
    public function __construct(string $chars = null)
    {
        $this->chars = $chars?? " \t\n\r\0\x0B";
    }

    /**
     * @inheritdoc
     */
    public function parse($value)
    {
        if (!is_string($value)) {
            return null;
        }

        return trim($value, $this->chars);
    }
}
