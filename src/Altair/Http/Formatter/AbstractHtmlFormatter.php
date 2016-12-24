<?php
namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;

abstract class AbstractHtmlFormatter implements OutputFormatterInterface
{
    /**
     * @return array
     */
    public static function accepts(): array
    {
        return ['text/html'];
    }

    /**
     * @return string
     */
    public function type(): string
    {
        return 'text/html';
    }
}
