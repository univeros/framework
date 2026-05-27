<?php

declare(strict_types=1);

namespace Altair\Tests\Introspection\Inspector;

use Altair\Container\Container;
use Altair\Introspection\Exception\NotFoundException;
use Altair\Introspection\Inspector\ContainerInspector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(ContainerInspector::class)]
class ContainerInspectorTest extends TestCase
{
    public function testInspectAllSurfacesAliasShareDelegateAndParameter(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, NullLogger::class);
        $container->share(NullLogger::class);
        $container->delegate(\stdClass::class, static fn(): \stdClass => new \stdClass());
        $container->defineParameter('appName', 'demo');

        $table = (new ContainerInspector($container))->inspectAll();

        $ids = array_map(
            static fn(mixed $v): string => strtolower((string) $v),
            array_column($table->rows, 'id'),
        );
        $this->assertContains(strtolower(LoggerInterface::class), $ids);
        $this->assertContains(strtolower(\stdClass::class), $ids);
        $this->assertContains('$appname', $ids);
        $this->assertSame(\count($table->rows), $table->extras['total']);
    }

    public function testInspectAllAppliesFilterAndSharedFlag(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, NullLogger::class);
        $container->share(NullLogger::class);
        $container->delegate(\stdClass::class, static fn(): \stdClass => new \stdClass());

        $filtered = (new ContainerInspector($container))->inspectAll(sharedOnly: true);
        $ids = array_map('strtolower', array_column($filtered->rows, 'id'));

        // The `--shared` filter reports bindings whose name is itself
        // registered as a singleton (i.e. via `share()`). Aliases get
        // their own rows but are not themselves shared — their target's
        // share status appears on the target's row. Compare names
        // case-insensitively because the Container normalizes them.
        $this->assertContains(strtolower(NullLogger::class), $ids);
        $this->assertNotContains(strtolower(\stdClass::class), $ids);

        $byName = (new ContainerInspector($container))->inspectAll(filter: 'Logger');
        $namesContainLogger = array_filter(
            $byName->rows,
            static fn(array $row): bool => stripos((string) $row['id'], 'Logger') === false,
        );
        $this->assertSame([], $namesContainLogger, 'All rows must match the filter substring.');
    }

    public function testInspectOneReportsAliasAndDependencies(): void
    {
        $container = new Container();
        $container->alias(LoggerInterface::class, NullLogger::class);

        $table = (new ContainerInspector($container))->inspectOne(LoggerInterface::class);

        $byField = [];
        foreach ($table->rows as $row) {
            $byField[$row['field']] = $row['value'];
        }

        $this->assertSame('alias', $byField['kind']);
        $this->assertSame(NullLogger::class, $byField['target']);
    }

    public function testInspectOneThrowsOnUnknownBinding(): void
    {
        $this->expectException(NotFoundException::class);
        (new ContainerInspector(new Container()))->inspectOne('Nonexistent\\Class');
    }
}
