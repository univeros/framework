<?php

declare(strict_types=1);

namespace Altair\Tests\Tinker\Repl;

use Altair\Tinker\Repl\PsyConfigurationFactory;
use Altair\Tinker\Repl\ReplContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsyConfigurationFactory::class)]
class PsyConfigurationFactoryTest extends TestCase
{
    public function testAppliesHistoryFileAndStartupMessage(): void
    {
        $historyFile = sys_get_temp_dir() . '/tinker_history_' . uniqid('', true);
        $context = new ReplContext(historyFile: $historyFile, historySize: 250);

        $configuration = (new PsyConfigurationFactory())->create($context, 'WELCOME BANNER');

        $this->assertSame($historyFile, $configuration->getHistoryFile());
        $this->assertSame('WELCOME BANNER', $configuration->getStartupMessage());
    }

    public function testEmptyStartupMessageIsNotForced(): void
    {
        $context = new ReplContext(historyFile: null);

        $configuration = (new PsyConfigurationFactory())->create($context, '');

        $this->assertNull($configuration->getStartupMessage());
    }
}
