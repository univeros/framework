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

    /**
     * @return array<string, mixed>
     */
    protected function getParsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (empty($body)) {
            return [];
        }

        if ($body instanceof JsonSerializable) {
            return (array) $body->jsonSerialize();
        }

        // if parsed body is an object but doesn't implement JsonSerializable use json parsing instead
        if (\is_object($body)) {
            $encoded = json_encode($body);

            if ($encoded === false) {
                return [];
            }

            return (array) json_decode($encoded, true);
        }

        return $body;
    }
}
