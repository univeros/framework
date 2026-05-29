<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Index\Builder;

use Altair\Index\Model\Usage;
use Altair\Index\Model\Symbol;
use Altair\Index\Builder\BuildResult;
use Altair\Index\Builder\IndexBuilder;
use Altair\Index\Builder\IndexConfig;
use Altair\Index\Builder\SourceScanner;
use Altair\Index\Query\UsageQuery;
use Altair\Index\Storage\Connection;
use Altair\Index\Storage\SqliteStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexBuilder::class)]
#[CoversClass(IndexConfig::class)]
#[CoversClass(SourceScanner::class)]
#[CoversClass(BuildResult::class)]
final class IndexBuilderTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-index-' . bin2hex(random_bytes(5));
        $this->write('src/User.php', <<<'PHP'
            <?php
            namespace App\Models;
            class User {}
            PHP);
        $this->write('src/UserRepository.php', <<<'PHP'
            <?php
            namespace App\Models;
            class UserRepository
            {
                public function make(): User { return new User(); }
            }
            PHP);
        $this->write('tests/UserTest.php', <<<'PHP'
            <?php
            namespace App\Tests;
            use App\Models\User;
            class UserTest
            {
                public function testIt(): void { $u = new User(); }
            }
            PHP);
        $this->write('api/user.yaml', <<<'YAML'
            endpoint:
              method: post
              path: /users
              summary: Create
            domain:
              class: App\Models\CreateUser
            persistence:
              entity:
                class: App\Models\User
                table: users
                fields:
                  id: { type: int, primary: true }
              repository: App\Models\UserRepository
            YAML);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function testFullBuildIndexesEverySourceAndSpecFile(): void
    {
        $result = $this->build(false);

        self::assertSame('full', $result->toArray()['mode']);
        self::assertSame(4, $result->filesScanned);
        self::assertSame(4, $result->filesIndexed);
        self::assertGreaterThan(0, $result->symbolCount);

        $usages = (new UsageQuery($this->open()))->usages('App\Models\User');
        $kinds = array_map(static fn(Usage $u): string => $u->kind->value, $usages);

        self::assertContains('new', $kinds);          // tests + repository
        self::assertContains('type_hint', $kinds);    // UserRepository::make return type
        self::assertContains('spec_entity', $kinds);  // the YAML spec
    }

    public function testIncrementalRebuildOnlyReWalksChangedFiles(): void
    {
        $this->build(false);

        // Touch one file's content; the rest are byte-identical.
        $this->write('src/UserRepository.php', <<<'PHP'
            <?php
            namespace App\Models;
            class UserRepository
            {
                public function make(): User { return new User(); }
                public function fresh(): User { return new User(); }
            }
            PHP);

        $result = $this->build(true);

        self::assertSame('incremental', $result->toArray()['mode']);
        self::assertSame(1, $result->filesIndexed);
        self::assertSame(3, $result->filesSkipped);
        self::assertSame(0, $result->filesRemoved);
    }

    public function testIncrementalRebuildDropsDeletedFiles(): void
    {
        $this->build(false);

        unlink($this->root . '/tests/UserTest.php');

        $result = $this->build(true);

        self::assertSame(1, $result->filesRemoved);

        $symbols = array_map(
            static fn(Symbol $s): string => $s->fqn,
            (new UsageQuery($this->open()))->unused(),
        );
        self::assertNotContains('App\Tests\UserTest', $symbols, 'deleted file symbols should be gone, not orphaned');
    }

    private function build(bool $incremental): BuildResult
    {
        $config = IndexConfig::forRoot($this->root);
        $storage = new SqliteStorage($this->open());

        return (new IndexBuilder($config, $storage, new SourceScanner($config)))->build($incremental);
    }

    private function open(): \PDO
    {
        return Connection::open($this->root . '/.altair/index.db');
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->root . '/' . $relative;
        if (!is_dir(\dirname($path))) {
            mkdir(\dirname($path), 0o755, true);
        }

        file_put_contents($path, $content);
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
