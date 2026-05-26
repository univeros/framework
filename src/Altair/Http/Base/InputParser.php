<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Base;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\InputInterface;
use JsonSerializable;
use Override;
use Psr\Http\Message\ServerRequestInterface;

class InputParser implements InputInterface
{
    /**
     * InputParser constructor.
     */
    public function __construct(protected InputCollection $inputCollection) {}

    #[Override]
    public function __invoke(ServerRequestInterface $request): InputCollection
    {
        $this->inputCollection->putAll(
            array_replace(
                $request->getAttributes(),
                $this->getParsedBody($request),
                $request->getCookieParams(),
                $request->getQueryParams(),
                $request->getUploadedFiles()
            )
        );

        return $this->inputCollection;
    }

    protected function getParsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (empty($body)) {
            return [];
        }

        return $body instanceof JsonSerializable
            ? $body->jsonSerialize()
            // if parsed body is an object but doesn't implements JsonSerializable use json parsing instead
            : (\is_object($body) ? json_decode(json_encode($body), true) : $body);
    }
}
