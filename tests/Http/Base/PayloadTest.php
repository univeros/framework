<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Base;

use Altair\Http\Base\Payload;
use Altair\Http\Collection\InputCollection;
use Altair\Http\Collection\SettingsCollection;
use PHPUnit\Framework\TestCase;

class PayloadTest extends TestCase
{
    public function testFreshPayloadHasNullStatusAndEmptyOutput(): void
    {
        $payload = new Payload();

        $this->assertNull($payload->getStatus());
        $this->assertSame([], $payload->getOutput());
        $this->assertSame([], $payload->getMessages());
        $this->assertInstanceOf(InputCollection::class, $payload->getInputCollection());
        $this->assertInstanceOf(SettingsCollection::class, $payload->getSettingsCollection());
    }

    public function testWithStatusReturnsNewInstanceWithStatus(): void
    {
        $original = new Payload();

        $next = $original->withStatus(201);

        $this->assertNotSame($original, $next);
        $this->assertSame(201, $next->getStatus());
        $this->assertNull($original->getStatus());
    }

    public function testWithOutputReturnsNewInstanceWithOutput(): void
    {
        $next = (new Payload())->withOutput(['a' => 1]);

        $this->assertSame(['a' => 1], $next->getOutput());
    }

    public function testWithMessagesReturnsNewInstanceWithMessages(): void
    {
        $next = (new Payload())->withMessages(['failure']);

        $this->assertSame(['failure'], $next->getMessages());
    }

    public function testWithSettingStoresAndRetrievesSetting(): void
    {
        $payload = (new Payload())->withSetting('foo', 'bar');

        $this->assertSame('bar', $payload->getSetting('foo'));
    }

    public function testWithoutSettingRemovesAStoredSetting(): void
    {
        $payload = (new Payload())->withSetting('foo', 'bar')->withoutSetting('foo');

        $this->assertNull($payload->getSetting('foo'));
    }
}
