<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Formatter;

use Altair\Http\Contracts\PayloadInterface;
use Altair\Http\Exception\InvalidArgumentException;
use Altair\Http\Exception\RuntimeException;
use Exception;
use Throwable;

class PhpViewFormatter extends AbstractHtmlFormatter
{
    protected string $templatesPath;



    /**
     * PhpViewFormatter constructor.
     */
    public function __construct(string $templatesPath, protected ?string $layout = null, protected string $defaultExtension = 'php')
    {
        if (!is_dir($templatesPath)) {
            throw new InvalidArgumentException(sprintf("'%s' is not a valid directory path.", $templatesPath));
        }

        $this->templatesPath = $templatesPath;
    }


    #[\Override]
    public function body(PayloadInterface $payload): string
    {
        return $this->render($payload);
    }

    /**
     * Renders the contents of a payload to the template specified with its settings.
     *
     *
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
     * @param $content
     *
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
     *
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
        } catch (Exception|Throwable $e) {
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
     *
     */
    protected function getViewFile(string $template): string
    {
        $file = $this->templatesPath . DIRECTORY_SEPARATOR . ltrim($template, '/');

        if (pathinfo($file, PATHINFO_EXTENSION) !== '') {
            return $file;
        }

        $path = $file . '.' . $this->defaultExtension;

        if ($this->defaultExtension !== 'php' && !is_file($path)) {
            return $file . '.php';
        }

        return $path;
    }
}
