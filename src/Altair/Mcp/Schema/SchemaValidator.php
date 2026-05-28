<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Schema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;
use stdClass;

/**
 * Validates tool input against a JSON Schema using opis/json-schema.
 *
 * A null schema means "no constraints" and always passes. An empty input array
 * is coerced to an empty object because tool arguments are JSON objects, and
 * {@see Helper::toJSON()} would otherwise render `[]` as a JSON array and fail a
 * `type: object` schema.
 */
final class SchemaValidator
{
    /**
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $schema
     */
    public function validate(array $data, ?array $schema): SchemaValidationResult
    {
        if ($schema === null) {
            return new SchemaValidationResult(true);
        }

        $payload = $data === [] ? new stdClass() : Helper::toJSON($data);
        $result = (new Validator())->validate($payload, $this->toSchema($schema));

        if ($result->isValid()) {
            return new SchemaValidationResult(true);
        }

        $error = $result->error();
        if (!$error instanceof ValidationError) {
            return new SchemaValidationResult(false, ['Input does not match schema.']);
        }

        $messages = array_values(array_filter(
            (new ErrorFormatter())->formatFlat($error),
            static fn(mixed $message): bool => \is_string($message) && $message !== '',
        ));

        return new SchemaValidationResult(false, $messages === [] ? ['Input does not match schema.'] : $messages);
    }

    /**
     * Convert a decoded-as-array JSON Schema into the object form opis expects.
     *
     * JSON-object keywords (`properties`, `$defs`, ...) must be objects even when
     * empty — but assoc-decoding renders an empty `{}` as `[]`. So every
     * associative or empty array becomes an object; only non-empty lists stay
     * arrays (e.g. `required`, `enum`).
     *
     * @param array<string, mixed> $schema
     */
    private function toSchema(array $schema): stdClass
    {
        $converted = $this->objectify($schema);

        return $converted instanceof stdClass ? $converted : new stdClass();
    }

    private function objectify(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if ($value !== [] && array_is_list($value)) {
            return array_map($this->objectify(...), $value);
        }

        $object = new stdClass();
        foreach ($value as $key => $item) {
            $object->{$key} = $this->objectify($item);
        }

        return $object;
    }
}
