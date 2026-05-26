<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecValidationException;
use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\Spec;

/**
 * Performs semantic validation on a parsed spec.
 *
 * - HTTP method is in the supported set
 * - Path starts with a slash
 * - Each input has a usable type
 * - Each input rule is known to Altair\Validation\Rule\*
 * - Every output status is a valid HTTP code
 * - Domain class is a well-formed FQCN
 */
class Validator
{
    private const array HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

    private const array SCALAR_TYPES = ['string', 'int', 'integer', 'float', 'bool', 'boolean', 'enum', 'array'];

    /**
     * Rules that ship with Altair\Validation\Rule\*Rule plus the bare "required"
     * marker the parser uses to flag mandatory fields.
     */
    private const array KNOWN_RULES = [
        'required', 'alphanum', 'alpha', 'between', 'boolean', 'callback',
        'creditcard', 'datetime', 'email', 'iban', 'in', 'integer', 'ip',
        'isbn', 'max', 'min', 'regex', 'swiftbic', 'url', 'zipcode',
    ];

    /**
     * @return list<string> Empty list when the spec is valid.
     */
    public function collectErrors(Spec $spec): array
    {
        $errors = [];

        if (!\in_array($spec->endpoint->method, self::HTTP_METHODS, true)) {
            $errors[] = \sprintf("endpoint.method '%s' is not a supported HTTP method.", $spec->endpoint->method);
        }

        if ($spec->endpoint->path === '' || $spec->endpoint->path[0] !== '/') {
            $errors[] = "endpoint.path must start with '/'.";
        }

        foreach ($spec->inputs as $input) {
            array_push($errors, ...$this->validateInput($input));
        }

        foreach ($spec->outputs as $output) {
            if ($output->status < 100 || $output->status > 599) {
                $errors[] = \sprintf("output status '%d' is not a valid HTTP status code.", $output->status);
            }
        }

        if (!$this->isFullyQualifiedClassName($spec->domain->class)) {
            $errors[] = \sprintf("domain.class '%s' is not a well-formed fully-qualified class name.", $spec->domain->class);
        }

        return $errors;
    }

    public function assertValid(Spec $spec): void
    {
        $errors = $this->collectErrors($spec);

        if ($errors !== []) {
            throw new SpecValidationException($errors);
        }
    }

    /**
     * @return list<string>
     */
    private function validateInput(InputFieldSpec $input): array
    {
        $errors = [];

        if (!\in_array($input->type, self::SCALAR_TYPES, true)) {
            $errors[] = \sprintf("input '%s' has unknown type '%s'.", $input->name, $input->type);
        }

        if ($input->isEnum() && !$this->isFullyQualifiedClassName($input->of ?? '')) {
            $errors[] = \sprintf("input '%s' enum target '%s' is not a fully-qualified class name.", $input->name, $input->of ?? '');
        }

        foreach ($input->rules as $rule) {
            $ruleName = strtolower(explode(':', $rule, 2)[0]);
            if (!\in_array($ruleName, self::KNOWN_RULES, true)) {
                $errors[] = \sprintf("input '%s' uses unknown validation rule '%s'.", $input->name, $rule);
            }
        }

        return $errors;
    }

    private function isFullyQualifiedClassName(string $class): bool
    {
        if ($class === '') {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z_]\w*(\\\[A-Za-z_]\w*)+$/', $class);
    }
}
