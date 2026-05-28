<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Support;

use Altair\Mcp\Support\PhpClassScanner;
use Altair\Tests\Mcp\Fixtures\Scanner\AnonBeforeNamed;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpClassScanner::class)]
final class PhpClassScannerTest extends TestCase
{
    public function testSkipsAnonymousClassAndReturnsNamedClass(): void
    {
        $fqcn = (new PhpClassScanner())->fqcnInFile(__DIR__ . '/../Fixtures/Scanner/AnonBeforeNamed.php');

        self::assertSame(AnonBeforeNamed::class, $fqcn);
    }

    public function testReturnsNullForFileWithoutClass(): void
    {
        $temp = (tempnam(sys_get_temp_dir(), 'mcp-noclass-') ?: '') . '.php';
        file_put_contents($temp, "<?php\n\n\$x = 1;\n");

        try {
            self::assertNull((new PhpClassScanner())->fqcnInFile($temp));
        } finally {
            @unlink($temp);
        }
    }

    public function testClassesInResolvesNamespacedClasses(): void
    {
        $classes = (new PhpClassScanner())->classesIn(__DIR__ . '/../Fixtures/Scanner');

        self::assertContains(AnonBeforeNamed::class, $classes);
    }
}
