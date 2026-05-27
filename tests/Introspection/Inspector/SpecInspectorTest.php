<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\SpecInspector;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpecInspector::class)]
class SpecInspectorTest extends TestCase
{
    private string $tmpRoot;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/altair-spec-inspector-' . bin2hex(random_bytes(4));
        @mkdir($this->tmpRoot . '/users', 0775, true);

        file_put_contents($this->tmpRoot . '/users/create.yaml', <<<YAML
endpoint:
  method: POST
  path: /users
YAML);
        file_put_contents($this->tmpRoot . '/users/list.yml', <<<YAML
endpoint:
  method: GET
  path: /users
YAML);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (glob($this->tmpRoot . '/users/*') ?: [] as $f) {
            @unlink($f);
        }

        @rmdir($this->tmpRoot . '/users');
        @rmdir($this->tmpRoot);
    }

    public function testInspectAllListsYamlSpecs(): void
    {
        $table = (new SpecInspector($this->tmpRoot))->inspectAll();

        $rows = $table->rows;
        $methods = array_column($rows, 'method');
        sort($methods);
        $this->assertSame(['GET', 'POST'], $methods);
        $this->assertSame(2, $table->extras['total']);
    }

    public function testInspectAllReturnsEmptyForMissingRoot(): void
    {
        $table = (new SpecInspector('/does/not/exist'))->inspectAll();
        $this->assertSame([], $table->rows);
        $this->assertFalse($table->extras['exists']);
    }

    public function testInspectOneFlattensNestedKeys(): void
    {
        $table = (new SpecInspector($this->tmpRoot))->inspectOne('users/create.yaml');
        $kv = [];
        foreach ($table->rows as $row) {
            $kv[$row['key']] = $row['value'];
        }

        $this->assertSame('POST', $kv['endpoint.method']);
        $this->assertSame('/users', $kv['endpoint.path']);
    }

    public function testInspectOneThrowsOnMissingFile(): void
    {
        $this->expectException(NotFoundException::class);
        (new SpecInspector($this->tmpRoot))->inspectOne('nope.yaml');
    }
}
