<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Observatory;

use Altair\Observatory\Contracts\PanelInterface;
use Altair\Observatory\Panel\PanelSnapshot;
use Altair\Observatory\Panel\PanelStatus;
use Altair\Observatory\PanelRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PanelRegistry::class)]
final class PanelRegistryTest extends TestCase
{
    public function testRegistersFromConstructorAndExposesInOrder(): void
    {
        $registry = new PanelRegistry([$this->panel('a'), $this->panel('b')]);

        self::assertCount(2, $registry->all());
        self::assertSame(['a', 'b'], array_map(static fn(PanelInterface $p): string => $p->id(), $registry->all()));
    }

    public function testGetAndHasByIdentifier(): void
    {
        $registry = new PanelRegistry([$this->panel('runtime')]);

        self::assertTrue($registry->has('runtime'));
        self::assertFalse($registry->has('missing'));
        self::assertInstanceOf(PanelInterface::class, $registry->get('runtime'));
        self::assertNull($registry->get('missing'));
    }

    public function testRegisteringSameIdReplaces(): void
    {
        $registry = new PanelRegistry();
        $registry->register($this->panel('runtime', 'First'));
        $registry->register($this->panel('runtime', 'Second'));

        self::assertCount(1, $registry->all());
        self::assertSame('Second', $registry->get('runtime')?->label());
    }

    private function panel(string $id, string $label = 'Label'): PanelInterface
    {
        return new readonly class ($id, $label) implements PanelInterface {
            public function __construct(private string $id, private string $label) {}

            public function id(): string
            {
                return $this->id;
            }

            public function label(): string
            {
                return $this->label;
            }

            public function icon(): string
            {
                return 'dot';
            }

            public function snapshot(): PanelSnapshot
            {
                return new PanelSnapshot(PanelStatus::Ok, 'ok');
            }
        };
    }
}
