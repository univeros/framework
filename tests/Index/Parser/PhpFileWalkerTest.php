<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Index\Parser;

use Altair\Index\Model\Symbol;
use Altair\Index\Model\SymbolKind;
use Altair\Index\Model\Usage;
use Altair\Index\Parser\PhpFileWalker;
use Altair\Index\Parser\SymbolUsageVisitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpFileWalker::class)]
#[CoversClass(SymbolUsageVisitor::class)]
#[CoversClass(Symbol::class)]
#[CoversClass(Usage::class)]
final class PhpFileWalkerTest extends TestCase
{
    private const string FIXTURE = <<<'PHP'
        <?php
        namespace App\Demo;

        use App\Base\BaseController;
        use App\Contracts\Handler;
        use App\Support\Logger;
        use App\Attr\Audit;

        #[Audit]
        class Widget extends BaseController implements Handler
        {
            public const STATUS = 200;
            public readonly string $label;
            private static int $count = 0;

            public function __construct(public Logger $logger) {}

            public function handle(Request $request): Response
            {
                $this->label = 'x';
                self::$count++;
                $n = $this->touched();
                Logger::configure();
                $code = self::STATUS;
                $cls = Widget::class;
                $log = new Logger();

                return new Response($n + $code);
            }

            private function touched(): int
            {
                return self::$count;
            }
        }
        PHP;

    public function testRecordsEverySymbolKindWithMetadata(): void
    {
        $symbols = $this->symbols(self::FIXTURE);

        $widget = $symbols['App\Demo\Widget'];
        self::assertSame(SymbolKind::Class_, $widget->kind);

        self::assertSame(SymbolKind::Constant, $symbols['App\Demo\Widget::STATUS']->kind);
        self::assertSame('public', $symbols['App\Demo\Widget::STATUS']->visibility);

        $label = $symbols['App\Demo\Widget::$label'];
        self::assertSame(SymbolKind::Property, $label->kind);
        self::assertTrue($label->isReadonly);

        $count = $symbols['App\Demo\Widget::$count'];
        self::assertTrue($count->isStatic);
        self::assertSame('private', $count->visibility);

        self::assertSame(SymbolKind::Method, $symbols['App\Demo\Widget::handle']->kind);
        self::assertSame('private', $symbols['App\Demo\Widget::touched']->visibility);

        // Constructor-promoted property is indexed as a property symbol.
        self::assertSame(SymbolKind::Property, $symbols['App\Demo\Widget::$logger']->kind);
        self::assertSame('public', $symbols['App\Demo\Widget::$logger']->visibility);
    }

    public function testRecordsAllSevenCoreUsageKinds(): void
    {
        $usages = $this->usageKeys(self::FIXTURE);

        self::assertContains('App\Base\BaseController|extends|App\Demo\Widget', $usages);
        self::assertContains('App\Contracts\Handler|implements|App\Demo\Widget', $usages);
        self::assertContains('App\Support\Logger|type_hint|App\Demo\Widget::__construct', $usages);
        self::assertContains('App\Demo\Request|type_hint|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Demo\Response|type_hint|App\Demo\Widget::handle', $usages);

        // call: $this-> and Class:: forms both resolve to a fully-qualified method.
        self::assertContains('App\Demo\Widget::touched|call|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Support\Logger::configure|call|App\Demo\Widget::handle', $usages);

        self::assertContains('App\Demo\Widget::$label|property_write|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Demo\Widget::$count|property_write|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Demo\Widget::$count|property_read|App\Demo\Widget::touched', $usages);

        $kinds = array_map(static fn(string $k): string => explode('|', $k)[1], $usages);
        foreach (['new', 'extends', 'implements', 'type_hint', 'call', 'property_read', 'property_write'] as $core) {
            self::assertContains($core, $kinds, sprintf("expected at least one '%s' usage", $core));
        }
    }

    public function testRecordsClassConstantAndAttributeAndNewUsages(): void
    {
        $usages = $this->usageKeys(self::FIXTURE);

        self::assertContains('App\Attr\Audit|attribute|App\Demo\Widget', $usages);
        self::assertContains('App\Support\Logger|new|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Demo\Response|new|App\Demo\Widget::handle', $usages);
        self::assertContains('App\Demo\Widget::STATUS|class_constant|App\Demo\Widget::handle', $usages);
        // `Widget::class` resolves to the class itself, not a named constant.
        self::assertContains('App\Demo\Widget|class_constant|App\Demo\Widget::handle', $usages);
    }

    public function testUntypedInstanceCallsAreNotLinked(): void
    {
        $code = <<<'PHP'
            <?php
            namespace App\Demo;

            class Service
            {
                public function run(object $dep): void
                {
                    $dep->doThing();
                }
            }
            PHP;

        $usages = $this->usageKeys($code);

        // No type inference: a call on an untyped variable produces no link.
        $calls = array_filter($usages, static fn(string $k): bool => str_contains($k, '|call|'));
        self::assertSame([], array_values($calls));
    }

    public function testUnparseableFileYieldsEmptyParsedFileWithHash(): void
    {
        $parsed = (new PhpFileWalker())->walk('broken.php', '<?php class {{{');

        self::assertSame([], $parsed->symbols);
        self::assertSame([], $parsed->usages);
        self::assertNotSame('', $parsed->hash);
    }

    /**
     * @return array<string, Symbol>
     */
    private function symbols(string $code): array
    {
        $parsed = (new PhpFileWalker())->walk('Widget.php', $code);
        $map = [];
        foreach ($parsed->symbols as $symbol) {
            $map[$symbol->fqn] = $symbol;
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function usageKeys(string $code): array
    {
        $parsed = (new PhpFileWalker())->walk('Widget.php', $code);

        return array_map(
            static fn(Usage $u): string => $u->fqn . '|' . $u->kind->value . '|' . ($u->context ?? ''),
            $parsed->usages,
        );
    }
}
