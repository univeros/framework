<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Configuration;

use Altair\Configuration\Support\Env;
use Altair\Messaging\Configuration\TransportSettings;
use Altair\Messaging\Exception\InvalidArgumentException;
use Override;
use PHPUnit\Framework\TestCase;

class TransportSettingsTest extends TestCase
{
    /** @var list<string> */
    private array $appliedKeys = [];

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->appliedKeys as $key) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);
        }

        $this->appliedKeys = [];
        parent::tearDown();
    }

    public function testParsesTransportsFromEnv(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'sync://',
            'MESSENGER_TRANSPORT_HIGH' => 'in-memory://',
        ]);

        $settings = TransportSettings::fromEnv(new Env());

        $this->assertTrue($settings->hasTransport('default'));
        $this->assertTrue($settings->hasTransport('high'));
        $this->assertSame('sync://', $settings->dsn('default'));
        $this->assertSame('in-memory://', $settings->dsn('high'));
        $this->assertEqualsCanonicalizing(['default', 'high'], $settings->names());
    }

    public function testRoutingParsing(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'sync://',
            'MESSENGER_ROUTING' => 'App\\Foo:default,App\\Bar:default|high',
        ]);

        $settings = TransportSettings::fromEnv(new Env());

        $this->assertSame(['default'], $settings->routing['App\\Foo']);
        $this->assertSame(['default', 'high'], $settings->routing['App\\Bar']);
    }

    public function testFailureTransportMustBeDeclared(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'sync://',
            'MESSENGER_FAILURE_TRANSPORT' => 'unknown',
        ]);

        $this->expectException(InvalidArgumentException::class);
        TransportSettings::fromEnv(new Env());
    }

    public function testMalformedRoutingIsRejected(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'sync://',
            'MESSENGER_ROUTING' => 'no-colon-here',
        ]);

        $this->expectException(InvalidArgumentException::class);
        TransportSettings::fromEnv(new Env());
    }

    public function testUnknownTransportThrows(): void
    {
        $settings = new TransportSettings(['default' => 'sync://']);
        $this->expectException(InvalidArgumentException::class);
        $settings->dsn('high');
    }

    /**
     * @param array<string, string> $values
     */
    private function setEnv(array $values): void
    {
        foreach ($values as $key => $value) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
            $this->appliedKeys[] = $key;
        }
    }
}
