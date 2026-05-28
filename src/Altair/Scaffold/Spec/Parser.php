<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec;

use Altair\Scaffold\Exception\SpecParseException;
use Altair\Scaffold\Spec\Ast\DomainSpec;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\InputFieldSpec;
use Altair\Scaffold\Spec\Ast\OutputResponseSpec;
use Altair\Scaffold\Spec\Ast\PersistenceEntitySpec;
use Altair\Scaffold\Spec\Ast\PersistenceFieldSpec;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\QueueDispatchSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Parses a YAML endpoint spec file into the internal Spec AST.
 */
class Parser
{
    public function parseFile(string $path): Spec
    {
        if (!is_file($path)) {
            throw new SpecParseException(\sprintf("Spec file '%s' does not exist.", $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new SpecParseException(\sprintf("Cannot read spec file '%s'.", $path));
        }

        return $this->parseString($contents, $path);
    }

    public function parseString(string $yaml, string $sourcePath = ''): Spec
    {
        try {
            $data = Yaml::parse($yaml);
        } catch (ParseException $parseException) {
            throw new SpecParseException(\sprintf('YAML parse error in %s: %s', $sourcePath ?: '<string>', $parseException->getMessage()), 0, $parseException);
        }

        if (!\is_array($data)) {
            throw new SpecParseException(\sprintf('Spec %s must be a YAML map at the top level.', $sourcePath ?: '<string>'));
        }

        return new Spec(
            endpoint: $this->parseEndpoint($this->requireMap($data, 'endpoint', $sourcePath)),
            inputs: $this->parseInputs($this->optionalMap($data, 'input')),
            outputs: $this->parseOutputs($this->optionalMap($data, 'output')),
            domain: $this->parseDomain($this->requireMap($data, 'domain', $sourcePath)),
            sourcePath: $sourcePath,
            persistence: isset($data['persistence'])
                ? $this->parsePersistence($this->requireMap($data, 'persistence', $sourcePath))
                : null,
            queue: $this->parseQueue($this->optionalMap($data, 'queue')),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parsePersistence(array $data): PersistenceSpec
    {
        if (!isset($data['entity']) || !\is_array($data['entity'])) {
            throw new SpecParseException("'persistence.entity' must be a map.");
        }

        $entityData = $data['entity'];
        $fieldsData = $entityData['fields'] ?? [];
        if (!\is_array($fieldsData)) {
            throw new SpecParseException("'persistence.entity.fields' must be a map.");
        }

        $fields = [];
        foreach ($fieldsData as $name => $raw) {
            if (!\is_array($raw)) {
                throw new SpecParseException(\sprintf("Persistence field '%s' must be a map.", $name));
            }

            $fields[] = new PersistenceFieldSpec(
                name: (string) $name,
                type: (string) ($raw['type'] ?? 'string'),
                primary: (bool) ($raw['primary'] ?? false),
                nullable: (bool) ($raw['nullable'] ?? false),
                unique: (bool) ($raw['unique'] ?? false),
                hasDefault: \array_key_exists('default', $raw),
                default: $raw['default'] ?? null,
                of: isset($raw['of']) ? (string) $raw['of'] : null,
            );
        }

        $entity = new PersistenceEntitySpec(
            class: (string) ($entityData['class'] ?? ''),
            table: (string) ($entityData['table'] ?? ''),
            fields: $fields,
        );

        return new PersistenceSpec(
            entity: $entity,
            repository: (string) ($data['repository'] ?? ''),
        );
    }

    /**
     * @param  array<int|string, mixed>   $data
     * @return list<QueueDispatchSpec>
     */
    private function parseQueue(array $data): array
    {
        $queue = [];
        foreach ($data as $name => $raw) {
            if (!\is_array($raw)) {
                throw new SpecParseException(\sprintf("queue entry '%s' must be a map.", $name));
            }

            $messageClass = (string) ($raw['message'] ?? '');
            if ($messageClass === '') {
                throw new SpecParseException(\sprintf("queue entry '%s' is missing required 'message' FQCN.", $name));
            }

            $fields = $raw['fields'] ?? [];
            if (!\is_array($fields)) {
                throw new SpecParseException(\sprintf("queue entry '%s' fields must be a map.", $name));
            }

            $fieldMap = [];
            foreach ($fields as $field => $type) {
                $fieldMap[(string) $field] = (string) $type;
            }

            $queue[] = new QueueDispatchSpec(
                name: (string) $name,
                message: $messageClass,
                fields: $fieldMap,
                transport: isset($raw['transport']) ? (string) $raw['transport'] : null,
            );
        }

        return $queue;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseEndpoint(array $data): EndpointSpec
    {
        $tags = $data['tags'] ?? [];
        if (!\is_array($tags)) {
            throw new SpecParseException("'endpoint.tags' must be a list.");
        }

        return new EndpointSpec(
            method: strtoupper((string) ($data['method'] ?? '')),
            path: (string) ($data['path'] ?? ''),
            summary: (string) ($data['summary'] ?? ''),
            tags: array_values(array_map(strval(...), $tags)),
        );
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<InputFieldSpec>
     */
    private function parseInputs(array $data): array
    {
        $inputs = [];
        foreach ($data as $name => $raw) {
            if (!\is_array($raw)) {
                throw new SpecParseException(\sprintf("Input field '%s' must be a map.", $name));
            }

            $rules = $raw['rules'] ?? [];
            if (!\is_array($rules)) {
                throw new SpecParseException(\sprintf("Input field '%s' rules must be a list.", $name));
            }

            $inputs[] = new InputFieldSpec(
                name: (string) $name,
                type: (string) ($raw['type'] ?? 'string'),
                rules: array_values(array_map(strval(...), $rules)),
                sensitive: (bool) ($raw['sensitive'] ?? false),
                of: isset($raw['of']) ? (string) $raw['of'] : null,
                default: $raw['default'] ?? null,
                hasDefault: \array_key_exists('default', $raw),
            );
        }

        return $inputs;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return list<OutputResponseSpec>
     */
    private function parseOutputs(array $data): array
    {
        $outputs = [];
        foreach ($data as $status => $raw) {
            if (!\is_array($raw)) {
                throw new SpecParseException(\sprintf("Output '%s' must be a map.", $status));
            }

            $body = $raw['body'] ?? [];
            if (!\is_array($body)) {
                throw new SpecParseException(\sprintf("Output '%s' body must be a map.", $status));
            }

            $bodyMap = [];
            foreach ($body as $field => $type) {
                $bodyMap[(string) $field] = (string) $type;
            }

            $outputs[] = new OutputResponseSpec(
                status: (int) $status,
                body: $bodyMap,
            );
        }

        return $outputs;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseDomain(array $data): DomainSpec
    {
        return new DomainSpec(
            class: (string) ($data['class'] ?? ''),
            invocation: (string) ($data['invocation'] ?? '__invoke'),
        );
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function requireMap(array $data, string $key, string $sourcePath): array
    {
        if (!isset($data[$key])) {
            throw new SpecParseException(\sprintf("Spec %s is missing required key '%s'.", $sourcePath ?: '<string>', $key));
        }

        if (!\is_array($data[$key])) {
            throw new SpecParseException(\sprintf("Spec key '%s' must be a map.", $key));
        }

        return $data[$key];
    }

    /**
     * @param  array<string, mixed> $data
     * @return array<int|string, mixed>
     */
    private function optionalMap(array $data, string $key): array
    {
        if (!isset($data[$key])) {
            return [];
        }

        if (!\is_array($data[$key])) {
            throw new SpecParseException(\sprintf("Spec key '%s' must be a map.", $key));
        }

        return $data[$key];
    }
}
