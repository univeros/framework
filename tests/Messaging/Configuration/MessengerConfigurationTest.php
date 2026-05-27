<?php

declare(strict_types=1);

namespace Altair\Tests\Messaging\Configuration;

use Symfony\Component\Messenger\Envelope;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Messaging\Configuration\MessengerConfiguration;
use Altair\Messaging\Configuration\TransportSettings;
use Altair\Messaging\Contracts\MessageBusInterface;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\HandlerLocator;
use Altair\Messaging\MessageBus;
use Altair\Messaging\Transport\TransportRegistry;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmail;
use Altair\Tests\Messaging\Fixtures\SendWelcomeEmailHandler;
use Override;
use PHPUnit\Framework\TestCase;

class MessengerConfigurationTest extends TestCase
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

    public function testApplyWiresMessageBusInterface(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'in-memory://',
            'MESSENGER_ROUTING' => SendWelcomeEmail::class . ':default',
        ]);

        $container = $this->bootContainer([__DIR__ . '/../Fixtures']);

        $bus = $container->make(MessageBusInterface::class);
        $this->assertInstanceOf(MessageBus::class, $bus);

        $altairBus = $container->make(MessageBus::class);
        $this->assertSame($bus, $altairBus);
    }

    public function testApplyResolvesHandlerRegistryFromAttributes(): void
    {
        $this->setEnv(['MESSENGER_TRANSPORT_DEFAULT' => 'sync://']);
        $container = $this->bootContainer([__DIR__ . '/../Fixtures']);

        /** @var HandlerRegistry $registry */
        $registry = $container->make(HandlerRegistry::class);
        $entries = $registry->handlersFor(SendWelcomeEmail::class);

        $this->assertCount(1, $entries);
        $this->assertSame(SendWelcomeEmailHandler::class, $entries[0]->handlerClass);
    }

    public function testTransportRegistryIsShared(): void
    {
        $this->setEnv(['MESSENGER_TRANSPORT_DEFAULT' => 'in-memory://']);
        $container = $this->bootContainer([]);

        $r1 = $container->make(TransportRegistry::class);
        $r2 = $container->make(TransportRegistry::class);
        $this->assertSame($r1, $r2);

        $transport = $r1->get('default');
        $this->assertSame($transport, $r1->get('default'), 'Transport must be cached.');
    }

    public function testSettingsExposedThroughContainer(): void
    {
        $this->setEnv([
            'MESSENGER_TRANSPORT_DEFAULT' => 'sync://',
            'MESSENGER_TRANSPORT_HIGH' => 'in-memory://',
        ]);
        $container = $this->bootContainer([]);

        /** @var TransportSettings $settings */
        $settings = $container->make(TransportSettings::class);
        $this->assertEqualsCanonicalizing(['default', 'high'], $settings->names());
    }

    public function testHandlerLocatorResolvesHandlersThroughContainer(): void
    {
        $this->setEnv(['MESSENGER_TRANSPORT_DEFAULT' => 'sync://']);
        $container = $this->bootContainer([__DIR__ . '/../Fixtures']);

        /** @var HandlerLocator $locator */
        $locator = $container->make(HandlerLocator::class);

        $envelope = new Envelope(new SendWelcomeEmail('u1', 'a@b.test'));
        $descriptors = iterator_to_array($locator->getHandlers($envelope), false);

        $this->assertCount(1, $descriptors);
        $this->assertSame(SendWelcomeEmailHandler::class, $descriptors[0]->getOption('alias'));
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

    /**
     * @param list<string> $handlerPaths
     */
    private function bootContainer(array $handlerPaths): Container
    {
        $container = new Container();
        $container->share(new Env());

        (new MessengerConfiguration($handlerPaths, allowNoHandlers: true))->apply($container);

        return $container;
    }
}
