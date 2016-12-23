<?php
namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;

class HtmlFormatter implements OutputFormatterInterface
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

    /**
     * @param PayloadInterface $payload
     *
     * @return string
     */
    public function body(PayloadInterface $payload): string
    {
        return $this->render($payload);
    }

    /**
     * Renders a view file as a PHP script.
     *
     *
     * @param PayloadInterface $payload
     *
     * @return string
     */
    protected function render(PayloadInterface $payload): string
    {
        $template = $payload->getSetting('template');
        ob_start();
        ob_implicit_flush(false);
        extract($payload->getOutput(), EXTR_OVERWRITE);
        require($template);

        return ob_get_clean();
    }
}
