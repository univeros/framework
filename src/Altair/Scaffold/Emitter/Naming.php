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
    /** 2000-01-01T00:00:00Z, expressed as a Unix timestamp. */
    private const int MIGRATION_STAMP_EPOCH = 946684800;

    /** ~10 years in seconds — the deterministic stamp window. */
    private const int MIGRATION_STAMP_WINDOW = 315360000;

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

    /**
     * Cycle's FileRepository expects filenames in the form
     * `<Ymd.His>_<chunk>_<name>.php` — the separator between date and time
     * is a dot, not an underscore (see Cycle\Migrations\FileRepository
     * ::TIMESTAMP_FORMAT). Using underscores throughout makes Cycle reject
     * the file with "corrupted date format" at migrator load time.
     */
    public function migrationPath(Spec $spec, ?int $timestamp = null): string
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return '';
        }

        $stamp = $timestamp ?? $this->deterministicMigrationStamp($spec);
        $date = gmdate('Ymd.His', $stamp);
        $table = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($spec->persistence->entity->table)) ?? 'table';

        return $this->migrationsRelativeRoot . '/' . $date . '_0_create_' . $table . '.php';
    }

    public function migrationClassName(Spec $spec, ?int $timestamp = null): string
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return 'Migration';
        }

        $stamp = $timestamp ?? $this->deterministicMigrationStamp($spec);
        $table = preg_replace('/[^a-zA-Z0-9_]+/', '_', $spec->persistence->entity->table) ?? 'table';

        return 'M' . gmdate('YmdHis', $stamp) . 'Create' . $this->camelize($table) . 'Table';
    }

    public function messagePath(string $messageFqcn): string
    {
        return $this->classFileRelativePath($messageFqcn);
    }

    public function handlerFqcn(string $messageFqcn): string
    {
        $namespace = $this->namespaceOf($messageFqcn);
        $short = $this->shortNameOf($messageFqcn) . 'Handler';

        return ($namespace === '' ? '' : $namespace . '\\') . $short;
    }

    public function handlerPath(string $messageFqcn): string
    {
        return $this->classFileRelativePath($this->handlerFqcn($messageFqcn));
    }

    public function handlerTestPath(string $messageFqcn): string
    {
        $short = $this->shortNameOf($this->handlerFqcn($messageFqcn)) . 'Test';

        return $this->testsRelativeRoot . '/Messages/' . $short . '.php';
    }

    public function handlerTestShortName(string $messageFqcn): string
    {
        return $this->shortNameOf($this->handlerFqcn($messageFqcn)) . 'Test';
    }

    /**
     * Content-addressed migration stamp — same spec produces the same
     * filename and class name across runs and machines (#74 determinism
     * standard). The stamp falls inside a fixed ~10-year window starting
     * 2000-01-01 UTC, so scaffolded CREATE TABLE migrations always sort
     * before any later wall-clock migration written by migration-intelligence
     * (#80) or hand-written by the host. Different entity tables get
     * different stamps via the sha256 spread, so Cycle's filename-ordered
     * migrator still runs them in a stable order.
     */
    private function deterministicMigrationStamp(Spec $spec): int
    {
        if (!$spec->persistence instanceof PersistenceSpec) {
            return self::MIGRATION_STAMP_EPOCH;
        }

        $hash = hexdec(substr(hash('sha256', $spec->persistence->entity->table), 0, 8));

        return self::MIGRATION_STAMP_EPOCH + $hash % self::MIGRATION_STAMP_WINDOW;
    }

    private function classFileRelativePath(string $fqcn): string
    {
        $relative = str_replace([$this->appNamespace . '\\', '\\'], ['', '/'], $fqcn);

        return $this->domainRelativeRoot . '/' . $relative . '.php';
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    private function shortNameOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
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
