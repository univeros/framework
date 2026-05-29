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
        $container->singleton(NullLogger::class);
        $container->factory(\stdClass::class, static fn(): \stdClass => new \stdClass());
        $container->value('appName', 'demo');

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
        $container->singleton(NullLogger::class);
        $container->factory(\stdClass::class, static fn(): \stdClass => new \stdClass());

        $filtered = (new ContainerInspector($container))->inspectAll(sharedOnly: true);
        $ids = array_map(strtolower(...), array_column($filtered->rows, 'id'));

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

    public function testInspectRealizedIncludesBuiltInstancesAndExcludesUnbuiltShares(): void
    {
        $container = new Container();
        // Registered as a singleton but never constructed → not realized yet.
        $container->singleton(NullLogger::class);
        // Registering an existing instance realizes it immediately.
        $container->instance(\ArrayObject::class, new \ArrayObject());

        $before = (new ContainerInspector($container))->inspectRealized();
        $idsBefore = array_map(strtolower(...), array_column($before->rows, 'id'));

        $this->assertContains(strtolower(\ArrayObject::class), $idsBefore, 'instance-shared services are realized at once');
        $this->assertNotContains(strtolower(NullLogger::class), $idsBefore, 'registered-but-unbuilt shares are not realized');

        // Resolving the shared logger via get() flips it to realized.
        $container->get(NullLogger::class);

        $after = (new ContainerInspector($container))->inspectRealized();
        $idsAfter = array_map(strtolower(...), array_column($after->rows, 'id'));
        $this->assertContains(strtolower(NullLogger::class), $idsAfter);

        $loggerRows = array_values(array_filter(
            $after->rows,
            static fn(array $row): bool => strtolower((string) $row['id']) === strtolower(NullLogger::class),
        ));
        $this->assertCount(1, $loggerRows);
        $this->assertSame(NullLogger::class, $loggerRows[0]['class'], 'class column carries the concrete runtime class');
        $this->assertSame(\count($after->rows), $after->extras['total']);
    }

    public function testInspectRealizedIsEmptyBeforeAnyInstantiation(): void
    {
        $container = new Container();
        $container->singleton(NullLogger::class); // registered only — nothing built yet

        $table = (new ContainerInspector($container))->inspectRealized();

        $this->assertTrue($table->isEmpty());
        $this->assertSame([], $table->rows);
        $this->assertSame(0, $table->extras['total']);
    }

    public function testInspectRealizedAppliesFilter(): void
    {
        $container = new Container();
        $container->instance(\ArrayObject::class, new \ArrayObject());
        $container->instance(\SplStack::class, new \SplStack());

        $table = (new ContainerInspector($container))->inspectRealized(filter: 'array');
        $ids = array_map(strtolower(...), array_column($table->rows, 'id'));

        $this->assertContains(strtolower(\ArrayObject::class), $ids);
        $this->assertNotContains(strtolower(\SplStack::class), $ids);
    }
}
