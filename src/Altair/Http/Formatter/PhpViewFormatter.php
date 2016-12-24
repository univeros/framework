<?php
namespace Altair\Http\Formatter;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Exception\InvalidArgumentException;

class PhpViewFormatter extends AbstractHtmlFormatter
{
    /**
     * @var string
     */
    protected $templatePath;
    /**
     * @var string
     */
    protected $defaultExtension;

    /**
     * PhpViewFormatter constructor.
     *
     * @param string $path
     * @param string $defaultExtension
     */
    public function __construct(string $path, string $defaultExtension = 'php')
    {
        if (!is_dir($path)) {
            throw new InvalidArgumentException("'{$path}' is not a valid directory path.");
        }
        $this->templatePath = $path;
        $this->defaultExtension = $defaultExtension;
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

    /**
     * Returns full path of a given template name
     *
     * @param string $template
     * @return string
     */
    protected function getViewFile(string $template): string
    {
        $file = $this->templatePath . DIRECTORY_SEPARATOR . ltrim($template, '/');

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }
        $path = $file . '.' . $this->defaultExtension;

        if ($this->defaultExtension !== 'php' && !is_file($path)) {
            $path = $file . '.php';
        }

        return $path;
    }
}
