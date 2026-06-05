<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Emitter;

use Altair\Scaffold\Exception\ScaffoldException;
use Altair\Scaffold\Sdk\Model\OperationModel;

/**
 * Derives filenames, domain class FQCNs, and resource hints from an
 * {@see OperationModel}. Holds the convention `api/<resource>/<verb>.yaml`
 * in one place so {@see Emitter} and tests can rely on it.
 */
final readonly class PathDeriver
{
    /**
     * Leading verbs that mark a path leaf as an RPC-style action rather than a
     * resource. Matched against the leaf's *first* camelCase word, so
     * `findByStatus`/`uploadImage`/`login` are actions while noun leaves like
     * `userProfiles` (first word `user`) or `findings` (one word, not `find`)
     * stay resources.
     */
    private const array ACTION_VERBS = [
        'get', 'list', 'find', 'search', 'fetch', 'create', 'add', 'update',
        'delete', 'remove', 'upload', 'download', 'login', 'logout', 'register',
        'refresh', 'verify', 'reset', 'send', 'callback', 'me',
    ];

    public function __construct(
        private string $appNamespace = 'App',
        private string $specRoot = 'api',
    ) {}

    public function filename(OperationModel $operation): string
    {
        return $this->specRoot . '/' . $this->resourceDir($operation) . '/' . $this->verb($operation) . '.yaml';
    }

    /**
     * Collision-free filename for every operation, resolved in one pass.
     *
     * The clean `api/<resource>/<verb>.yaml` name is kept when unique; when two
     * distinct operations would derive the same name — e.g. the Swagger
     * Petstore's `PUT /pet` (updatePet) and `POST /pet/{petId}`
     * (updatePetWithForm), both "update pet" — each falls back to its
     * operationId, which OpenAPI guarantees unique. Two operations sharing one
     * operationId is rejected: it is an invalid spec whose generated domain
     * classes would collide too.
     *
     * @param  list<OperationModel>   $operations
     * @return array<string, string>  {@see operationKey} => relative filename
     */
    public function resolveFilenames(array $operations): array
    {
        $this->assertUniqueOperationIds($operations);

        $frequency = [];
        foreach ($operations as $operation) {
            $name = $this->filename($operation);
            $frequency[$name] = ($frequency[$name] ?? 0) + 1;
        }

        $result = [];
        $used = [];
        foreach ($operations as $operation) {
            $name = $this->filename($operation);
            if (($frequency[$name] ?? 0) > 1) {
                $name = $this->specRoot . '/' . $this->resourceDir($operation) . '/' . $this->disambiguator($operation) . '.yaml';
            }

            $name = $this->ensureUnique($name, $used);
            $used[$name] = true;
            $result[$this->operationKey($operation)] = $name;
        }

        return $result;
    }

    /**
     * Stable identity for an operation (unique within a valid OpenAPI document):
     * the key {@see resolveFilenames} maps to a filename.
     */
    public function operationKey(OperationModel $operation): string
    {
        return strtoupper($operation->method) . ' ' . $operation->path;
    }

    public function domainFqcn(OperationModel $operation): string
    {
        $resource = $this->singularize($this->resourceSegment($operation));
        $namespacePart = $this->pascalCase($resource);
        $className = $this->className($operation);

        return $this->appNamespace . '\\' . $namespacePart . '\\' . $className;
    }

    public function resourceSingular(OperationModel $operation): string
    {
        return $this->singularize($this->resourceSegment($operation));
    }

    public function resourceDir(OperationModel $operation): string
    {
        return $this->resourceSegment($operation);
    }

    public function verb(OperationModel $operation): string
    {
        $firstWord = $this->firstCamelWord($operation->operationId);
        if ($firstWord !== '') {
            return strtolower($firstWord);
        }

        return $this->verbFromMethod($operation);
    }

    private function verbFromMethod(OperationModel $operation): string
    {
        return match (strtoupper($operation->method)) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            'GET' => $this->endsWithPathParameter($operation->path) ? 'get' : 'list',
            default => strtolower($operation->method),
        };
    }

    private function className(OperationModel $operation): string
    {
        if ($operation->operationId !== '') {
            return $this->pascalCase($operation->operationId);
        }

        $verb = $this->verbFromMethod($operation);
        $resourceSegment = $this->resourceSegment($operation);
        $resource = $verb === 'list' ? $resourceSegment : $this->singularize($resourceSegment);

        return $this->pascalCase($verb) . $this->pascalCase($resource);
    }

    private function resourceSegment(OperationModel $operation): string
    {
        $segments = array_values(array_filter(
            explode('/', $operation->path),
            static fn(string $segment): bool => $segment !== '' && !str_starts_with($segment, '{'),
        ));

        if ($segments === []) {
            return 'endpoint';
        }

        // Walk back past RPC-style action leaves (findByStatus, uploadImage,
        // login) to the nearest noun resource — an action is not a resource and
        // must not become the class namespace. `/pet/findByStatus` → `pet`.
        for ($i = \count($segments) - 1; $i >= 0; --$i) {
            if (!$this->isActionSegment($segments[$i])) {
                return $segments[$i];
            }
        }

        return $segments[\count($segments) - 1];
    }

    /**
     * Whether a path leaf reads as an action (verb/RPC) rather than a resource
     * (noun): its first camelCase word is a known verb. `findByStatus` → `find`
     * (action); `userProfiles` → `user` (resource); `findings` → `findings`
     * (one word, not `find` → resource).
     */
    private function isActionSegment(string $segment): bool
    {
        return \in_array(strtolower($this->firstCamelWord($segment)), self::ACTION_VERBS, true);
    }

    private function endsWithPathParameter(string $path): bool
    {
        $segments = array_values(array_filter(
            explode('/', $path),
            static fn(string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return false;
        }

        return str_starts_with(end($segments), '{');
    }

    private function firstCamelWord(string $value): string
    {
        if ($value === '') {
            return '';
        }

        // Split on camelCase boundary: first lowercase run.
        if (preg_match('/^[a-z]+/', $value, $match) === 1) {
            return $match[0];
        }

        return $value;
    }

    /**
     * @param list<OperationModel> $operations
     */
    private function assertUniqueOperationIds(array $operations): void
    {
        $seen = [];
        foreach ($operations as $operation) {
            $id = $operation->operationId;
            if ($id === '') {
                continue;
            }

            if (isset($seen[$id])) {
                throw new ScaffoldException(\sprintf(
                    "Duplicate operationId '%s' on '%s' and '%s'. operationIds must be unique — each maps to one domain class.",
                    $id,
                    $seen[$id],
                    $this->operationKey($operation),
                ));
            }

            $seen[$id] = $this->operationKey($operation);
        }
    }

    private function disambiguator(OperationModel $operation): string
    {
        if ($operation->operationId !== '') {
            return $this->kebabCase($operation->operationId);
        }

        return $this->verb($operation) . '-' . strtolower($operation->method);
    }

    /**
     * @param array<string, true> $used
     */
    private function ensureUnique(string $name, array $used): string
    {
        if (!isset($used[$name])) {
            return $name;
        }

        $base = substr($name, 0, -\strlen('.yaml'));
        $suffix = 2;
        while (isset($used[$base . '-' . $suffix . '.yaml'])) {
            $suffix++;
        }

        return $base . '-' . $suffix . '.yaml';
    }

    private function kebabCase(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z])(?=[A-Z])/', $value) ?: [];
        $words = array_filter($words, static fn(string $w): bool => $w !== '');

        return implode('-', array_map(strtolower(...), $words));
    }

    private function pascalCase(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+|(?<=[a-z])(?=[A-Z])/', $value) ?: [];
        $words = array_filter($words, static fn(string $w): bool => $w !== '');

        return implode('', array_map(static fn(string $w): string => ucfirst(strtolower($w)), $words));
    }

    private function singularize(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        // Never singularize an action/RPC leaf: stripping the trailing 's' of
        // "findByStatus" would yield "findByStatu".
        if ($this->isActionSegment($value)) {
            return $value;
        }

        if (str_ends_with($value, 'ies') && \strlen($value) > 3) {
            return substr($value, 0, -3) . 'y';
        }

        if (str_ends_with($value, 'ses') && \strlen($value) > 3) {
            return substr($value, 0, -2);
        }

        return str_ends_with($value, 's') && !str_ends_with($value, 'ss')
            ? substr($value, 0, -1)
            : $value;
    }
}
