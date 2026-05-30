<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Examples\Configuration\ExamplesConfiguration;
use Altair\Examples\Configuration\ExamplesSettings;
use Altair\Examples\Library\Contracts\ExampleRepositoryInterface;
use Altair\Examples\Library\ExampleParser;
use Altair\Examples\Library\ExampleRepository;
use Altair\Examples\Library\IndexBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExamplesConfiguration::class)]
#[CoversClass(ExamplesSettings::class)]
final class ExamplesConfigurationTest extends TestCase
{
    private string $tmpRoot;

    /** @var list<string> */
    private array $appliedKeys = [];

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/altair-examples-cfg-' . bin2hex(random_bytes(4));
        mkdir($this->tmpRoot, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->appliedKeys as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }
        $this->appliedKeys = [];

        @rmdir($this->tmpRoot);
    }

    public function testResolvesEveryServiceFromTheContainer(): void
    {
        $container = $this->bootContainer();

        self::assertInstanceOf(ExamplesSettings::class, $container->make(ExamplesSettings::class));
        self::assertInstanceOf(ExampleParser::class, $container->make(ExampleParser::class));
        self::assertInstanceOf(ExampleRepository::class, $container->make(ExampleRepositoryInterface::class));
        self::assertInstanceOf(IndexBuilder::class, $container->make(IndexBuilder::class));
    }

    public function testSettingsPathDefaultsToAltairExamples(): void
    {
        $container = $this->bootContainer();
        $settings = $container->make(ExamplesSettings::class);

        self::assertSame($this->tmpRoot . '/.altair/examples', $settings->libraryPath());
        self::assertSame($this->tmpRoot . '/.altair/examples/index.json', $settings->indexPath());
    }

    public function testSettingsHonourEnvironmentOverrides(): void
    {
        $this->setEnv([
            'ALTAIR_EXAMPLES_DIR' => 'storage',
            'ALTAIR_EXAMPLES_LIBRARY_DIR' => 'patterns',
            'ALTAIR_EXAMPLES_INDEX_FILE' => 'manifest.json',
        ]);

        $container = $this->bootContainer();
        $settings = $container->make(ExamplesSettings::class);

        self::assertSame($this->tmpRoot . '/storage/patterns', $settings->libraryPath());
        self::assertSame($this->tmpRoot . '/storage/patterns/manifest.json', $settings->indexPath());
    }

    private function bootContainer(): Container
    {
        $container = new Container();
        $container->instance(Env::class, new Env());

        (new ExamplesConfiguration(projectRoot: $this->tmpRoot))->apply($container);

        return $container;
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv(sprintf('%s=%s', $key, $value));
            $this->appliedKeys[] = $key;
        }
    }
}
