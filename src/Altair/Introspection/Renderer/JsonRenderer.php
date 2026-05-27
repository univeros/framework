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
use Altair\Introspection\Result\InspectionTable;
use JsonException;
use Override;

/**
 * Pretty-printed JSON renderer.
 *
 * Output is deterministic — same {@see InspectionTable} always produces
 * byte-identical output, so CI can diff against a golden fixture.
 */
final readonly class JsonRenderer implements RendererInterface
{
    #[Override]
    public function render(InspectionTable $table): string
    {
        try {
            return json_encode(
                $table->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            ) . "\n";
        } catch (JsonException $jsonException) {
            throw new IntrospectionException('Inspection table is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}
