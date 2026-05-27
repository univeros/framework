<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Renderer;

use Altair\Introspection\Contracts\RendererInterface;
use Altair\Introspection\Exception\IntrospectionException;

/**
 * Resolves the right renderer for a CLI command's `--format` flag.
 *
 * `human` and `json` ship out of the box; hosts can pre-bind their own
 * renderer (HTML, CSV, etc.) into the registry before bootstrapping
 * the CLI.
 */
final readonly class RendererRegistry
{
    /**
     * @param array<string, RendererInterface> $renderers
     */
    public function __construct(
        private array $renderers,
    ) {}

    public static function default(): self
    {
        return new self([
            'human' => new TableRenderer(),
            'json' => new JsonRenderer(),
        ]);
    }

    public function get(string $format): RendererInterface
    {
        if (!isset($this->renderers[$format])) {
            throw new IntrospectionException(\sprintf(
                "Unknown output format '%s'. Available: %s.",
                $format,
                implode(', ', array_keys($this->renderers)),
            ));
        }

        return $this->renderers[$format];
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->renderers);
    }
}
