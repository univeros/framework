<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Spec\Emitter;

use Altair\Scaffold\Sdk\Model\OperationModel;

/**
 * Derives filenames, domain class FQCNs, and resource hints from an
 * {@see OperationModel}. Holds the convention `api/<resource>/<verb>.yaml`
 * in one place so {@see Emitter} and tests can rely on it.
 */
final readonly class PathDeriver
{
    public function __construct(
        private string $appNamespace = 'App',
        private string $specRoot = 'api',
    ) {}

    public function filename(OperationModel $operation): string
    {
        return $this->specRoot . '/' . $this->resourceDir($operation) . '/' . $this->verb($operation) . '.yaml';
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

        return end($segments);
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
