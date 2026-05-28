<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Input;

use Altair\Http\Exception\InputValidationException;
use BackedEnum;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use TypeError;

/**
 * Builds a typed, readonly input DTO from a PSR-7 request.
 *
 * Request data is merged (query string, then parsed body, then route
 * attributes win) and mapped onto the DTO constructor by parameter name. Scalar
 * values are coerced to the declared builtin type and backed-enum parameters are
 * resolved with `tryFrom()`. Anything that cannot satisfy the declared type — a
 * missing required field, a non-numeric value for an `int`, an unknown enum case
 * — is collected into an {@see InputValidationException} (mapped to HTTP 422)
 * rather than being allowed to surface as a `TypeError` (HTTP 500).
 *
 * This is the bridge that lets `spec:scaffold`-generated DTO inputs execute
 * through {@see \Altair\Http\Middleware\ActionMiddleware} without implementing
 * the request-bag {@see \Altair\Http\Contracts\InputInterface}.
 */
final class DtoInputHydrator
{
    /**
     * @param class-string $dtoClass
     */
    public function hydrate(string $dtoClass, ServerRequestInterface $request): object
    {
        $reflection = new ReflectionClass($dtoClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflection->newInstance();
        }

        $data = $this->collect($request);

        $arguments = [];
        $errors = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (\array_key_exists($name, $data)) {
                $arguments[] = $this->coerce($data[$name], $parameter, $errors);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $arguments[] = null;
                continue;
            }

            $errors[$name] = 'is required';
        }

        if ($errors !== []) {
            throw new InputValidationException($errors);
        }

        // Safety net: a value passed through to a non-builtin, non-enum type
        // (class / union / intersection) that does not satisfy it surfaces as a
        // 422 rather than an uncaught TypeError (HTTP 500).
        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (TypeError $typeError) {
            throw new InputValidationException(
                ['input' => 'has one or more fields of the wrong type'],
                $typeError->getMessage(),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function collect(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        // Route attributes win over body, which wins over the query string.
        // Framework-internal attributes are colon-namespaced (e.g.
        // `altair:http:action`) so they can never match a DTO property name.
        return array_replace(
            $request->getQueryParams(),
            \is_array($body) ? $body : [],
            $request->getAttributes(),
        );
    }

    /**
     * @param array<string, string> $errors
     */
    private function coerce(mixed $value, ReflectionParameter $parameter, array &$errors): mixed
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || $value === null) {
            return $value;
        }

        $name = $type->getName();

        if ($type->isBuiltin()) {
            return $this->coerceBuiltin($value, $name, $parameter->getName(), $errors);
        }

        if (enum_exists($name) && is_a($name, BackedEnum::class, true)) {
            $case = (\is_int($value) || \is_string($value)) ? $name::tryFrom($value) : null;
            if ($case === null) {
                $errors[$parameter->getName()] = 'is not a valid value';

                return null;
            }

            return $case;
        }

        return $value;
    }

    /**
     * @param array<string, string> $errors
     */
    private function coerceBuiltin(mixed $value, string $type, string $field, array &$errors): mixed
    {
        switch ($type) {
            case 'int':
                if (\is_int($value)) {
                    return $value;
                }

                if (\is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    return (int) $value;
                }

                $errors[$field] = 'must be an integer';

                return null;
            case 'float':
                if (\is_int($value) || \is_float($value)) {
                    return (float) $value;
                }

                if (\is_string($value) && is_numeric($value)) {
                    return (float) $value;
                }

                $errors[$field] = 'must be a number';

                return null;
            case 'bool':
                if (\is_bool($value)) {
                    return $value;
                }

                $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($bool === null) {
                    $errors[$field] = 'must be a boolean';

                    return null;
                }

                return $bool;
            case 'string':
                if (\is_string($value)) {
                    return $value;
                }

                if (\is_int($value) || \is_float($value) || \is_bool($value)) {
                    return (string) $value;
                }

                $errors[$field] = 'must be a string';

                return null;
            case 'array':
                if (\is_array($value)) {
                    return $value;
                }

                $errors[$field] = 'must be an array';

                return null;
            default:
                return $value;
        }
    }
}
