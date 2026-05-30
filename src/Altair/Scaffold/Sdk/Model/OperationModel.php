<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Sdk\Model;

/**
 * One HTTP operation: method + path + request/response shapes.
 *
 * `operationId` is the canonical camelCase name the emitters use for the
 * generated function/method. When the OpenAPI document doesn't carry an
 * explicit `operationId`, the parser synthesises one from method + path
 * (`POST /users` → `createUser`, `GET /users/{id}` → `getUsersById`).
 */
final readonly class OperationModel
{
    /**
     * @param list<string>        $pathParameters Names of `{param}` path segments, in order.
     * @param list<ResponseModel> $responses
     * @param array<string, mixed> $extensions    `x-altair-*` keys carried verbatim from the OpenAPI document.
     */
    public function __construct(
        public string $operationId,
        public string $method,
        public string $path,
        public array $pathParameters,
        public ?SchemaType $requestBody,
        public array $responses,
        public string $summary = '',
        public array $extensions = [],
    ) {}

    public function hasRequestBody(): bool
    {
        return $this->requestBody instanceof SchemaType;
    }

    /**
     * @return list<ResponseModel>
     */
    public function successResponses(): array
    {
        return array_values(array_filter($this->responses, static fn(ResponseModel $r): bool => $r->isSuccess()));
    }
}
