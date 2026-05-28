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
use Altair\Http\Exception\InvalidArgumentException;
use JsonException;
use Override;

class JsonContentMiddleware extends AbstractContentHandlerMiddleware
{
    /**
     * @var int<1, max>
     */
    private readonly int $maxDepth;

    /**
     * @param int<1, max> $maxDepth
     */
    public function __construct(
        private readonly bool $associative = true,
        int $maxDepth = 512,
        private readonly int $flags = 0,
    ) {
        if ($maxDepth < 1) {
            throw new InvalidArgumentException('The maximum decoding depth must be at least 1.');
        }

        $this->maxDepth = $maxDepth;
    }

    #[Override]
    protected function contentTypes(): array
    {
        return ['application/json', 'text/json', 'application/x-json'];
    }

    #[Override]
    protected function parse(string $body): array|object|null
    {
        try {
            return json_decode($body, $this->associative, $this->maxDepth, $this->flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new HttpBadRequestException($jsonException->getMessage(), $jsonException->getCode(), previous: $jsonException);
        }
    }
}
