<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Messaging\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Messaging\Contracts\MessageBusInterface;
use Altair\Messaging\Discovery\AttributeHandlerDiscoverer;
use Altair\Messaging\Discovery\HandlerRegistry;
use Altair\Messaging\HandlerLocator;
use Altair\Messaging\MessageBus;
use Altair\Messaging\Middleware\ContainerHandlerMiddleware;
use Altair\Messaging\Transport\FailureSenderContainer;
use Altair\Messaging\Transport\LazyBus;
use Altair\Messaging\Transport\TransportLocator;
use Altair\Messaging\Transport\TransportRegistry;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherInterface;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\MessageBus as SymfonyMessageBus;
use Symfony\Component\Messenger\MessageBusInterface as SymfonyMessageBusInterface;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Wires Symfony Messenger into the Altair Container.
 *
 * - Parses transports + routing from environment via TransportSettings
 * - Discovers handlers from #[AsHandler] in the supplied paths
 * - Composes a single Symfony MessageBus with SendMessage + Handle middleware
 * - Exposes that bus via Altair\Messaging\Contracts\MessageBusInterface
 *
 * Optional dependencies are detected reflectively so the host application
 * only needs to install transport bridges it actually uses
 * (symfony/redis-messenger, symfony/doctrine-messenger, symfony/amqp-messenger).
 */
final readonly class MessengerConfiguration implements ConfigurationInterface
{
    /**
     * @param list<string> $handlerPaths Directories scanned for #[AsHandler] classes
     */
    public function __construct(
        private array $handlerPaths = [],
        private bool $allowNoHandlers = false,
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $handlerPaths = $this->handlerPaths;
        $allowNoHandlers = $this->allowNoHandlers;

        $container
            ->delegate(
                TransportSettings::class,
                static fn(Env $env): TransportSettings => TransportSettings::fromEnv($env),
            )
            ->share(TransportSettings::class)
            ->delegate(
                PhpSerializer::class,
                static fn(): PhpSerializer => new PhpSerializer(),
            )
            ->share(PhpSerializer::class)
            ->alias(SerializerInterface::class, PhpSerializer::class)
            ->delegate(
                TransportFactory::class,
                static fn(): TransportFactory => new TransportFactory(self::collectTransportFactories(new LazyBus($container))),
            )
            ->share(TransportFactory::class)
            ->alias(TransportFactoryInterface::class, TransportFactory::class)
            ->delegate(
                TransportRegistry::class,
                static fn(
                    TransportSettings $settings,
                    TransportFactoryInterface $factory,
                    SerializerInterface $serializer,
                ): TransportRegistry => new TransportRegistry($settings, $factory, $serializer),
            )
            ->share(TransportRegistry::class)
            ->delegate(
                TransportLocator::class,
                static fn(TransportRegistry $registry): TransportLocator => new TransportLocator($registry),
            )
            ->share(TransportLocator::class)
            ->delegate(
                EventDispatcher::class,
                static fn(): EventDispatcher => new EventDispatcher(),
            )
            ->share(EventDispatcher::class)
            ->alias(SymfonyEventDispatcherInterface::class, EventDispatcher::class)
            ->delegate(
                HandlerRegistry::class,
                static fn(
                    AttributeHandlerDiscoverer $discoverer,
                ): HandlerRegistry => $discoverer->buildRegistry($handlerPaths),
            )
            ->share(HandlerRegistry::class)
            ->share(AttributeHandlerDiscoverer::class)
            ->delegate(
                HandlerLocator::class,
                static fn(
                    HandlerRegistry $registry,
                ): HandlerLocator => new HandlerLocator($container, $registry),
            )
            ->share(HandlerLocator::class)
            ->delegate(
                SendersLocator::class,
                static fn(
                    TransportSettings $settings,
                    TransportLocator $locator,
                ): SendersLocator => new SendersLocator($settings->routing, $locator),
            )
            ->share(SendersLocator::class)
            ->delegate(
                SendMessageMiddleware::class,
                static fn(
                    SendersLocator $senders,
                    SymfonyEventDispatcherInterface $dispatcher,
                ): SendMessageMiddleware => new SendMessageMiddleware($senders, $dispatcher),
            )
            ->share(SendMessageMiddleware::class)
            ->delegate(
                ContainerHandlerMiddleware::class,
                static fn(
                    HandlerLocator $locator,
                    ?LoggerInterface $logger,
                ): ContainerHandlerMiddleware => new ContainerHandlerMiddleware(
                    $locator,
                    $allowNoHandlers,
                    $logger,
                ),
            )
            ->share(ContainerHandlerMiddleware::class)
            ->delegate(
                SymfonyMessageBus::class,
                static fn(
                    SendMessageMiddleware $send,
                    ContainerHandlerMiddleware $handle,
                ): SymfonyMessageBus => new SymfonyMessageBus([$send, $handle]),
            )
            ->share(SymfonyMessageBus::class)
            ->alias(SymfonyMessageBusInterface::class, SymfonyMessageBus::class)
            ->delegate(
                MessageBus::class,
                static fn(SymfonyMessageBusInterface $bus): MessageBus => new MessageBus($bus),
            )
            ->share(MessageBus::class)
            ->alias(MessageBusInterface::class, MessageBus::class);

        $this->registerFailureListener($container);
        $this->registerLoggerFallback($container);
    }

    /**
     * @return list<TransportFactoryInterface<TransportInterface>>
     */
    private static function collectTransportFactories(LazyBus $lazyBus): array
    {
        $factories = [
            new SyncTransportFactory($lazyBus),
            new InMemoryTransportFactory(),
        ];

        $optional = [
            'Symfony\\Component\\Messenger\\Bridge\\Redis\\Transport\\RedisTransportFactory',
            'Symfony\\Component\\Messenger\\Bridge\\Doctrine\\Transport\\DoctrineTransportFactory',
            'Symfony\\Component\\Messenger\\Bridge\\AmqpExt\\Transport\\AmqpTransportFactory',
            'Symfony\\Component\\Messenger\\Bridge\\Amqp\\Transport\\AmqpTransportFactory',
            'Symfony\\Component\\Messenger\\Bridge\\Beanstalkd\\Transport\\BeanstalkdTransportFactory',
        ];

        foreach ($optional as $class) {
            if (class_exists($class)) {
                /** @var TransportFactoryInterface<TransportInterface> $instance */
                $instance = new $class();
                $factories[] = $instance;
            }
        }

        return $factories;
    }

    private function registerFailureListener(Container $container): void
    {
        $container
            ->delegate(
                FailureSenderContainer::class,
                static function (
                    TransportSettings $settings,
                    TransportRegistry $registry,
                ): FailureSenderContainer {
                    if ($settings->failureTransport === null) {
                        return new FailureSenderContainer([]);
                    }

                    return new FailureSenderContainer(
                        $settings->names(),
                        $registry->get($settings->failureTransport),
                    );
                },
            )
            ->share(FailureSenderContainer::class)
            ->delegate(
                SendFailedMessageToFailureTransportListener::class,
                static fn(
                    FailureSenderContainer $senders,
                    ?LoggerInterface $logger,
                ): SendFailedMessageToFailureTransportListener => new SendFailedMessageToFailureTransportListener($senders, $logger),
            )
            ->share(SendFailedMessageToFailureTransportListener::class);
    }

    private function registerLoggerFallback(Container $container): void
    {
        if ($container->isset(LoggerInterface::class)) {
            return;
        }

        $container->share(new NullLogger());
        $container->alias(LoggerInterface::class, NullLogger::class);
    }
}
