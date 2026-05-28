<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Observatory\View;

use Altair\Observatory\Exception\ObservatoryException;

/**
 * A minimal, dependency-free PHP template renderer.
 *
 * Templates are plain PHP files under resources/views that receive a single
 * `$data` array (no symbol extraction), so they stay easy to reason about and
 * the renderer carries no engine baggage. The view files themselves are
 * presentation-only and excluded from static analysis.
 */
final readonly class TemplateRenderer
{
    public function __construct(private string $viewPath) {}

    public static function default(): self
    {
        return new self(\dirname(__DIR__) . '/resources/views');
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ObservatoryException when the template is missing
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->viewPath . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new ObservatoryException(\sprintf('Observatory view "%s" not found.', $template));
        }

        $capture = static function (string $__file, array $data): string {
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        };

        return $capture($file, $data);
    }

    /**
     * Escape a value for safe HTML output.
     */
    public static function e(int|float|string|bool|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
