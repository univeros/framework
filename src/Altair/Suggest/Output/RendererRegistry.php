<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Suggest\Output;

use Altair\Suggest\Contracts\SuggestionRendererInterface;
use Altair\Suggest\Exception\SuggestException;

/**
 * Resolves the right renderer for the `--format` flag. `human` and `json`
 * ship by default; hosts can pre-bind their own before bootstrapping.
 */
final readonly class RendererRegistry
{
    /**
     * @param array<string, SuggestionRendererInterface> $renderers
     */
    public function __construct(
        private array $renderers,
    ) {}

    public static function default(): self
    {
        return new self([
            'human' => new HumanRenderer(),
            'json' => new JsonRenderer(),
        ]);
    }

    public function get(string $format): SuggestionRendererInterface
    {
        return $this->renderers[$format] ?? throw new SuggestException(\sprintf(
            "Unknown output format '%s'. Available: %s.",
            $format,
            implode(', ', array_keys($this->renderers)),
        ));
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        return array_keys($this->renderers);
    }
}
