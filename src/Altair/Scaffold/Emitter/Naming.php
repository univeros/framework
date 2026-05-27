<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Emitter;

use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;

/**
 * Centralizes how artifact short-names, namespaces, and file paths are
 * derived from a Spec. Keeps the convention "PascalCase + suffix" consistent
 * across every emitter.
 */
final readonly class Naming
{
    public function __construct(
        private string $appNamespace = 'App',
        private string $httpRelativeRoot = 'app/Http',
        private string $domainRelativeRoot = 'app',
        private string $testsRelativeRoot = 'tests',
        private string $openApiRelativeRoot = 'docs/openapi',
        private string $routesPath = 'config/routes.php',
        private string $migrationsRelativeRoot = 'database/migrations',
    ) {}

    public function actionShortName(Spec $spec): string
    {
        return $spec->artifactName() . 'Action';
    }

    public function inputShortName(Spec $spec): string
    {
        return $spec->artifactName() . 'Input';
    }

    public function responderShortName(Spec $spec): string
    {
        return $spec->artifactName() . 'Responder';
    }

    public function testShortName(Spec $spec): string
    {
        return $this->actionShortName($spec) . 'Test';
    }

    public function actionFqcn(Spec $spec): string
    {
        return $this->appNamespace . '\\Http\\Actions\\' . $this->actionShortName($spec);
    }

    public function inputFqcn(Spec $spec): string
    {
        return $this->appNamespace . '\\Http\\Inputs\\' . $this->inputShortName($spec);
    }

    public function responderFqcn(Spec $spec): string
    {
        return $this->appNamespace . '\\Http\\Responders\\' . $this->responderShortName($spec);
    }

    public function domainFqcn(Spec $spec): string
    {
        return $spec->domain->class;
    }

    public function actionPath(Spec $spec): string
    {
        return $this->httpRelativeRoot . '/Actions/' . $this->actionShortName($spec) . '.php';
    }

    public function inputPath(Spec $spec): string
    {
        return $this->httpRelativeRoot . '/Inputs/' . $this->inputShortName($spec) . '.php';
    }

    public function responderPath(Spec $spec): string
    {
        return $this->httpRelativeRoot . '/Responders/' . $this->responderShortName($spec) . '.php';
    }

    public function domainPath(Spec $spec): string
    {
        $relative = str_replace([$this->appNamespace . '\\', '\\'], ['', '/'], $spec->domain->class);

        return $this->domainRelativeRoot . '/' . $relative . '.php';
    }

    public function testPath(Spec $spec): string
    {
        return $this->testsRelativeRoot . '/Http/Actions/' . $this->testShortName($spec) . '.php';
    }

    public function openApiPath(Spec $spec): string
    {
        $slug = $this->slugify($spec);

        return $this->openApiRelativeRoot . '/' . $slug . '.yaml';
    }

    public function routesPath(): string
    {
        return $this->routesPath;
    }

    public function appNamespace(): string
    {
        return $this->appNamespace;
    }

    public function entityPath(Spec $spec): string
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return '';
        }

        return $this->classFileRelativePath($spec->persistence->entity->class);
    }

    public function repositoryPath(Spec $spec): string
    {
        if (!$spec->persistence instanceof PersistenceSpec || $spec->persistence->repository === '') {
            return '';
        }

        return $this->classFileRelativePath($spec->persistence->repository);
    }

    public function migrationPath(Spec $spec, ?int $timestamp = null): string
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return '';
        }

        $stamp = ($timestamp ?? time());
        $date = gmdate('Ymd_His', $stamp);
        $table = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($spec->persistence->entity->table)) ?? 'table';

        return $this->migrationsRelativeRoot . '/' . $date . '_create_' . $table . '.php';
    }

    public function migrationClassName(Spec $spec, ?int $timestamp = null): string
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return 'Migration';
        }

        $stamp = ($timestamp ?? time());
        $table = preg_replace('/[^a-zA-Z0-9_]+/', '_', $spec->persistence->entity->table) ?? 'table';

        return 'M' . gmdate('YmdHis', $stamp) . 'Create' . $this->camelize($table) . 'Table';
    }

    private function classFileRelativePath(string $fqcn): string
    {
        $relative = str_replace([$this->appNamespace . '\\', '\\'], ['', '/'], $fqcn);

        return $this->domainRelativeRoot . '/' . $relative . '.php';
    }

    private function camelize(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($value))));
    }

    private function slugify(Spec $spec): string
    {
        $name = strtolower(preg_replace('/(?<!^)([A-Z])/', '-$1', $spec->artifactName()) ?? $spec->artifactName());

        return trim((string) $name, '-');
    }
}
