<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Persistence\Contracts\EntityManagerInterface;
use Altair\Persistence\Contracts\RepositoryInterface;
use Altair\Persistence\Contracts\UnitOfWorkInterface;
use Altair\Persistence\Cycle\CycleEntityManager;
use Altair\Persistence\Cycle\CycleUnitOfWork;
use Altair\Persistence\Schema\SchemaProviderInterface;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\Factory;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Override;

/**
 * Binds the full Cycle ORM stack into the Altair Container.
 *
 * Wiring order:
 *
 * 1. {@see DatabaseSettings} — built from {@see Env} once.
 * 2. {@see DatabaseManager} — shared, built from settings.
 * 3. {@see ORMInterface} — shared, built from a {@see SchemaProviderInterface}.
 * 4. {@see UnitOfWorkInterface} → {@see CycleUnitOfWork} — shared.
 * 5. {@see EntityManagerInterface} → {@see CycleEntityManager} — shared,
 *    with optional `class-string => RepositoryInterface` bindings.
 *
 * A {@see SchemaProviderInterface} MUST be bound separately by the host
 * application (typically a build-time pre-compiled schema or
 * {@see \Altair\Persistence\Schema\AttributeSchemaProvider}).
 */
final readonly class CycleOrmConfiguration implements ConfigurationInterface
{
    private const array DB_ENV_KEYS = [
        'DB_CONNECTION',
        'DB_DATABASE',
        'DB_HOST',
        'DB_PORT',
        'DB_USER',
        'DB_PASSWORD',
        'DB_CHARSET',
    ];

    /**
     * @param array<class-string, class-string<RepositoryInterface<object>>> $repositoryBindings
     */
    public function __construct(
        private array $repositoryBindings = [],
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $container
            ->share(DatabaseSettings::class)
            ->share(DatabaseManager::class)
            ->share(CycleUnitOfWork::class)
            ->share(CycleEntityManager::class)
            ->share(ORMInterface::class)
            ->alias(DatabaseProviderInterface::class, DatabaseManager::class)
            ->alias(UnitOfWorkInterface::class, CycleUnitOfWork::class)
            ->alias(EntityManagerInterface::class, CycleEntityManager::class);

        $container->delegate(
            DatabaseSettings::class,
            static fn(Env $env): DatabaseSettings => DatabaseSettings::fromEnv(self::readEnv($env)),
        );

        $container->delegate(
            DatabaseManager::class,
            static fn(DatabaseSettings $settings): DatabaseManager
                => (new DatabaseConnectionFactory())->create($settings),
        );

        $container->delegate(
            ORMInterface::class,
            static fn(DatabaseProviderInterface $databases, SchemaProviderInterface $provider): ORMInterface
                => new ORM(new Factory($databases), new Schema($provider->schema())),
        );

        $bindings = $this->repositoryBindings;
        $container->delegate(
            CycleEntityManager::class,
            static fn(
                ORMInterface $orm,
                UnitOfWorkInterface $unitOfWork,
                Container $resolver,
            ): CycleEntityManager => new CycleEntityManager($orm, $unitOfWork, $resolver, $bindings),
        );

        foreach ($bindings as $repository) {
            $container->share($repository);
        }
    }

    /**
     * @return array<string, string|null>
     */
    private static function readEnv(Env $env): array
    {
        $values = [];
        foreach (self::DB_ENV_KEYS as $key) {
            $value = $env->get($key);
            $values[$key] = $value === null ? null : (string) $value;
        }

        return $values;
    }
}
