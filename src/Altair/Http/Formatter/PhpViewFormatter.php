<?php
namespace Altair\Http\Formatter;

use Altair\Http\Configuration\PhpViewConfiguration;
use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\RuntimeException;
use Exception;
use Throwable;

class PhpViewFormatter extends AbstractHtmlFormatter
{
    /**
     * @var string
     */
    protected $templatesPath;
    /**
     * @var string
     */
    protected $layout;
    /**
     * @var string
     */
    protected $defaultExtension;

    /**
     * PhpViewFormatter constructor.
     *
     * @param string $templatesPath
     * @param string $layout
     * @param string $defaultExtension
     */
    public function __construct(string $templatesPath, string $layout = null, string $defaultExtension = 'php')
    {
        if (!is_dir($templatesPath)) {
            throw new InvalidArgumentException("'{$templatesPath}' is not a valid directory path.");
        }

        $this->templatesPath = $templatesPath;
        $this->layout = $layout;
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
     * Renders the contents of a payload to the template specified with its settings.
     *
     * @param PayloadInterface $payload
     *
     * @return string
     * @throws Throwable
     */
    protected function render(PayloadInterface $payload): string
    {
        $template = $payload->getSetting('template');

        $file = $this->getViewFile($template);

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('Template file "%s" not found.', $file));
        }

        $content = $this->renderPhpFile($file, $payload->getOutput());

        return $this->renderLayout($payload, $content);
    }

    /**
     * Renders the contents to a layout specified within the payload settings. If not layout is found, it will use the
     * one configured.
     *
     * @param PayloadInterface $payload
     * @param $content
     *
     * @return string
     *
     * @see PhpViewConfiguration::apply()
     */
    protected function renderLayout(PayloadInterface $payload, $content): string
    {
        $layout = $payload->getSetting('layout') ?: $this->layout;

        if (null !== $layout) {
            $file = $this->getViewFile($layout);
            if (!is_file($file)) {
                throw new RuntimeException(sprintf('Layout file "%s" not found.', $file));
            }

            $params = $payload->getOutput();
            $params['content'] = $content;

            return $this->renderPhpFile($file, $params);
        }

        return $content;
    }

    /**
     * Renders a view file as a PHP script.
     *
     * @param string $file
     * @param array $params
     *
     * @return string
     * @throws Exception
     * @throws Throwable
     */
    protected function renderPhpFile(string $file, array $params = []): string
    {
        $level = ob_get_level();
        ob_start();
        ob_implicit_flush(false);
        extract($params, EXTR_OVERWRITE);

        try {
            require($file);

            return ob_get_clean();
        } catch (Exception $e) {
            while (ob_get_level() > $level) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                if (!@ob_end_clean()) {
                    ob_clean();
                }
            }
            throw $e;
        }
    }

    /**
     * Returns full path of a given template name
     *
     * @param string $template
     *
     * @return string
     */
    protected function getViewFile(string $template): string
    {
        $file = $this->templatesPath . DIRECTORY_SEPARATOR . ltrim($template, '/');

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
