<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Exception\HttpBadRequestException;
use JsonException;

class JsonContentMiddleware extends AbstractContentHandlerMiddleware
{
    public function __construct(
        private readonly bool $associative = true,
        private readonly int $maxDepth = 512,
        private readonly int $flags = 0,
    ) {
    }

    protected function contentTypes(): array
    {
        return ['application/json', 'text/json', 'application/x-json'];
    }

    protected function parse(string $body): array|object|null
    {
        try {
            return json_decode($body, $this->associative, $this->maxDepth, $this->flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new HttpBadRequestException($e->getMessage(), previous: $e);
        }
    }
}
