<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Index\Cli;

use Altair\Index\Builder\IndexConfig;
use Altair\Index\Cli\BuildCommand;
use Altair\Index\Cli\FindUsagesCommand;
use Altair\Index\Cli\ImpactCommand;
use Altair\Index\Cli\ImplementsCommand;
use Altair\Index\Cli\OrphansCommand;
use Altair\Index\Cli\UnusedCommand;
use Altair\Index\Support\ProjectIndex;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BuildCommand::class)]
#[CoversClass(FindUsagesCommand::class)]
#[CoversClass(ImplementsCommand::class)]
#[CoversClass(ImpactCommand::class)]
#[CoversClass(UnusedCommand::class)]
#[CoversClass(OrphansCommand::class)]
#[CoversClass(ProjectIndex::class)]
final class CommandsTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/altair-index-cli-' . bin2hex(random_bytes(5));
        $this->write('src/Animal.php', "<?php\nnamespace App\\Pets;\ninterface Animal {}\n");
        $this->write('src/Dog.php', <<<'PHP'
            <?php
            namespace App\Pets;
            class Dog implements Animal
            {
                public function bark(): string { return 'woof'; }
                public function speak(): string { return $this->bark(); }
            }
            PHP);
        $this->write('src/Unused.php', "<?php\nnamespace App\\Pets;\nclass Unused {}\n");
        $this->write('tests/DogTest.php', <<<'PHP'
            <?php
            namespace App\Tests;
            use App\Pets\Dog;
            class DogTest { public function testBark(): void { $d = new Dog(); } }
            PHP);
        $this->write('api/pet.yaml', <<<'YAML'
            endpoint: { method: post, path: /pets, summary: Create }
            domain: { class: App\Pets\CreatePet }
            persistence:
              entity: { class: App\Pets\Missing, table: pets, fields: { id: { type: int, primary: true } } }
              repository: App\Pets\PetRepository
            YAML);

        $this->invoke(new BuildCommand($this->index()), []);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function testBuildReportsTotals(): void
    {
        [$exit, $output] = $this->invoke(new BuildCommand($this->index()), ['incremental' => true]);

        self::assertSame(0, $exit);
        self::assertStringContainsString('symbols', $output);
    }

    public function testFindUsagesJsonListsReferences(): void
    {
        [$exit, $output] = $this->invoke(
            new FindUsagesCommand($this->index()),
            ['symbol' => 'App\Pets\Dog', 'format' => 'json', 'noBuild' => true],
        );

        self::assertSame(0, $exit);
        $data = json_decode($output, true);
        self::assertSame('App\Pets\Dog', $data['symbol']);
        self::assertGreaterThanOrEqual(1, $data['count']);
    }

    public function testImplementsReturnsImplementingClass(): void
    {
        [, $output] = $this->invoke(
            new ImplementsCommand($this->index()),
            ['interface' => 'App\Pets\Animal', 'format' => 'json', 'noBuild' => true],
        );

        $data = json_decode($output, true);
        self::assertSame(['App\Pets\Dog'], $data['implementers']);
    }

    public function testImpactEnumeratesAffectedTests(): void
    {
        [, $output] = $this->invoke(
            new ImpactCommand($this->index()),
            ['symbols' => 'App\Pets\Dog', 'format' => 'json', 'noBuild' => true],
        );

        $data = json_decode($output, true);
        self::assertContains('tests/DogTest.php', $data['tests_to_run']);
    }

    public function testUnusedStrictExitsNonZeroWhenCandidatesExist(): void
    {
        [$exit, $output] = $this->invoke(
            new UnusedCommand($this->index()),
            ['format' => 'json', 'strict' => true, 'noBuild' => true],
        );

        self::assertSame(1, $exit);
        $fqns = array_column(json_decode($output, true)['symbols'], 'fqn');
        self::assertContains('App\Pets\Unused', $fqns);
    }

    public function testOrphansExitsNonZeroForDanglingSpecTarget(): void
    {
        [$exit, $output] = $this->invoke(
            new OrphansCommand($this->index()),
            ['format' => 'json', 'noBuild' => true],
        );

        self::assertSame(1, $exit);
        $fqns = array_column(json_decode($output, true)['orphans'], 'fqn');
        self::assertContains('App\Pets\Missing', $fqns);
    }

    public function testQueryWithoutIndexAndNoBuildBails(): void
    {
        $emptyRoot = sys_get_temp_dir() . '/altair-index-empty-' . bin2hex(random_bytes(5));
        $index = new ProjectIndex(IndexConfig::forRoot($emptyRoot));

        [$exit, $output] = $this->invoke(new FindUsagesCommand($index), ['symbol' => 'X', 'noBuild' => true]);

        self::assertSame(2, $exit);
        self::assertStringContainsString('index:build', $output);
    }

    private function index(): ProjectIndex
    {
        return new ProjectIndex(IndexConfig::forRoot($this->root));
    }

    /**
     * @param array<string, mixed> $args
     *
     * @return array{0: int, 1: string}
     */
    private function invoke(callable $command, array $args): array
    {
        ob_start();
        $exit = (int) $command(...$args);
        $output = (string) ob_get_clean();

        return [$exit, $output];
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
