<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Output;

use Altair\MigrationIntelligence\Contracts\PlanRendererInterface;
use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;

/**
 * Resolves the renderer for a `--format` flag. `human` and `json` ship by
 * default; hosts may supply their own.
 */
final readonly class RendererRegistry
{
    /**
     * @param array<string, PlanRendererInterface> $renderers
     */
    public function __construct(private array $renderers) {}

    public static function default(): self
    {
        return new self([
            'human' => new HumanRenderer(),
            'json' => new JsonRenderer(),
        ]);
    }

    public function get(string $format): PlanRendererInterface
    {
        return $this->renderers[$format] ?? throw new MigrationIntelligenceException(\sprintf(
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
