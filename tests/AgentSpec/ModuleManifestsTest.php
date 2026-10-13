<?php

declare(strict_types=1);

namespace Altair\Tests\AgentSpec;

use Altair\AgentSpec\Exception\AgentSpecException;
use Altair\AgentSpec\Generator\IndexGenerator;
use Altair\AgentSpec\Generator\ManifestPipeline;
use Altair\AgentSpec\Generator\ManifestPipelineOptions;
use Altair\AgentSpec\Generator\ModuleManifests;
use Altair\AgentSpec\Model\ModuleManifest;
use Altair\Container\Container;
use Altair\Module\Contracts\ManifestContributorInterface;
use Altair\Module\Contracts\ModuleInterface;
use Altair\Module\ModuleConfiguration;
use Altair\Tests\AgentSpec\Support\FixturePaths;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleManifests::class)]
#[CoversClass(ModuleManifest::class)]
#[CoversClass(ManifestPipeline::class)]
#[CoversClass(IndexGenerator::class)]
final class ModuleManifestsTest extends TestCase
{
    public function testCollectReturnsContributorsSortedByNameAndSkipsOthers(): void
    {
        $container = new Container();
        (new ModuleConfiguration([
            new ZetaModule('acme/zeta', 'Zeta.', "# Zeta\n"),
            new PlainModule(),
            new AlphaModule('acme/alpha', 'Alpha.', "# Alpha"),
        ]))->apply($container);

        $manifests = ModuleManifests::collect($container);

        self::assertSame(['acme/alpha', 'acme/zeta'], array_map(static fn(ModuleManifest $m): string => $m->name, $manifests));
        self::assertSame("# Alpha\n", $manifests[0]->contents);
        self::assertSame('modules/acme/alpha.md', $manifests[0]->path());
    }

    public function testCollectWithoutContainerIsEmpty(): void
    {
        self::assertSame([], ModuleManifests::collect(null));
    }

    #[DataProvider('validNames')]
    public function testModuleManifestAcceptsShortAndVendorNames(string $name): void
    {
        self::assertSame('modules/' . $name . '.md', (new ModuleManifest($name, 'd', 'c'))->path());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validNames(): iterable
    {
        yield 'short' => ['user-management'];
        yield 'vendor' => ['univeros/polaris'];
    }

    #[DataProvider('unsafeNames')]
    public function testModuleManifestRejectsNamesThatEscapeTheModulesDirectory(string $name): void
    {
        $this->expectException(AgentSpecException::class);

        new ModuleManifest($name, 'd', 'c');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeNames(): iterable
    {
        yield 'traversal' => ['../etc/passwd'];
        yield 'nested traversal' => ['acme/../../x'];
        yield 'absolute' => ['/acme/x'];
        yield 'three segments' => ['a/b/c'];
        yield 'uppercase' => ['Acme/Module'];
        yield 'empty' => [''];
    }

    public function testIndexIsUnchangedWithoutModules(): void
    {
        $generator = new IndexGenerator();

        self::assertSame($generator->render([]), $generator->render([], modules: []));
    }

    public function testIndexListsModulesWhenThereAreNoFrameworkPackages(): void
    {
        $rendered = (new IndexGenerator())->render([], modules: [new ModuleManifest('univeros/polaris', 'Auth | RBAC.', 'x')]);

        self::assertStringNotContainsString('| Package | Namespace |', $rendered);
        self::assertStringContainsString("## Modules\n", $rendered);
        self::assertStringContainsString('| [univeros/polaris](modules/univeros/polaris.md) | Auth \| RBAC. |', $rendered);
    }

    public function testPipelineWritesModuleSectionsInAHostWithoutFrameworkSources(): void
    {
        $outputRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'altair-agentspec-' . bin2hex(random_bytes(8));
        $modules = [new ModuleManifest('univeros/polaris', 'Auth.', "# Polaris\n")];

        try {
            $pipeline = new ManifestPipeline();
            $written = $pipeline->run($this->hostOptions($outputRoot, false, $modules));

            self::assertSame(['modules/univeros/polaris.md', 'MANIFEST.md'], $written);
            self::assertStringEqualsFile($outputRoot . '/modules/univeros/polaris.md', "# Polaris\n");
            self::assertSame([], $pipeline->run($this->hostOptions($outputRoot, true, $modules)));

            $changed = [new ModuleManifest('univeros/polaris', 'Auth.', "# Polaris v2\n")];
            self::assertSame(['modules/univeros/polaris.md'], $pipeline->run($this->hostOptions($outputRoot, true, $changed)));
        } finally {
            @unlink($outputRoot . '/modules/univeros/polaris.md');
            @unlink($outputRoot . '/MANIFEST.md');
            @rmdir($outputRoot . '/modules/univeros');
            @rmdir($outputRoot . '/modules');
            @rmdir($outputRoot);
        }
    }

    public function testPipelineStillFailsWithNeitherPackagesNorModules(): void
    {
        $this->expectException(AgentSpecException::class);

        (new ManifestPipeline())->run($this->hostOptions(sys_get_temp_dir(), true, []));
    }

    public function testPipelineWithFrameworkPackagesAddsModulesAfterThem(): void
    {
        $outputRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'altair-agentspec-' . bin2hex(random_bytes(8));

        try {
            (new ManifestPipeline())->run(new ManifestPipelineOptions(
                monorepoRoot: FixturePaths::monorepoRoot(),
                sourceRoot: FixturePaths::sourceRoot(),
                testsRoot: FixturePaths::testsRoot(),
                outputRoot: $outputRoot,
                checkOnly: false,
                modules: [new ModuleManifest('acme/billing', 'Billing.', '# Billing')],
            ));

            $index = (string) file_get_contents($outputRoot . '/MANIFEST.md');
            self::assertStringContainsString('[univeros/sample-package](packages/sample-package.md)', $index);
            self::assertStringContainsString("|\n\n## Modules\n", $index);
            self::assertFileExists($outputRoot . '/modules/acme/billing.md');
        } finally {
            exec('rm -rf ' . escapeshellarg($outputRoot));
        }
    }

    /**
     * @param list<ModuleManifest> $modules
     */
    private function hostOptions(string $outputRoot, bool $checkOnly, array $modules): ManifestPipelineOptions
    {
        $missing = $outputRoot . DIRECTORY_SEPARATOR . 'no-such-src';

        return new ManifestPipelineOptions(
            monorepoRoot: $outputRoot,
            sourceRoot: $missing,
            testsRoot: null,
            outputRoot: $outputRoot,
            checkOnly: $checkOnly,
            modules: $modules,
        );
    }
}

abstract readonly class ContributingModule implements ModuleInterface, ManifestContributorInterface
{
    public function __construct(private string $name, private string $description, private string $body) {}

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function apply(Container $container): void {}

    #[Override]
    public function manifestDescription(): string
    {
        return $this->description;
    }

    #[Override]
    public function manifest(): string
    {
        return $this->body;
    }
}

final readonly class AlphaModule extends ContributingModule {}

final readonly class ZetaModule extends ContributingModule {}

final class PlainModule implements ModuleInterface
{
    #[Override]
    public function name(): string
    {
        return 'plain';
    }

    #[Override]
    public function apply(Container $container): void {}
}
