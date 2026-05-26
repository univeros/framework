<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Sanitation\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Sanitation\Configuration\SanitationConfiguration;
use Altair\Sanitation\Contracts\FiltersRunnerInterface;
use Altair\Sanitation\Contracts\ResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SanitationConfiguration::class)]
final class SanitationConfigurationTest extends TestCase
{
    public function testClassExistsUnderSanitationNamespace(): void
    {
        self::assertTrue(
            class_exists(SanitationConfiguration::class),
            'SanitationConfiguration must be reachable via Altair\\Sanitation\\Configuration\\* (PSR-4).',
        );
    }

    public function testImplementsConfigurationInterface(): void
    {
        self::assertInstanceOf(ConfigurationInterface::class, new SanitationConfiguration());
    }

    public function testApplyWiresSanitationContractsToConcretes(): void
    {
        $container = new Container();
        (new SanitationConfiguration())->apply($container);

        self::assertInstanceOf(ResolverInterface::class, $container->make(ResolverInterface::class));
        self::assertInstanceOf(FiltersRunnerInterface::class, $container->make(FiltersRunnerInterface::class));
    }
}
