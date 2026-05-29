<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Configuration;

use Altair\Configuration\Contracts\ConfigurationInterface;
use Altair\Container\Container;
use Altair\Doctor\Configuration\DoctorConfiguration;
use Altair\Doctor\Doctor;
use Altair\Events\Configuration\EventsConfiguration;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Mcp\Contracts\ToolResolverInterface;
use Altair\Mcp\Database\CycleDatabaseGateway;
use Altair\Mcp\Database\DatabaseGatewayInterface;
use Altair\Mcp\Database\NullDatabaseGateway;
use Altair\Mcp\Guard\PathGuard;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Schema\SchemaValidator;
use Altair\Mcp\Server\Server;
use Altair\Mcp\Server\ServerInfo;
use Altair\Mcp\Support\EventLog;
use Altair\Mcp\Support\ProjectContext;
use Altair\Mcp\Tool\AttributeToolDiscoverer;
use Altair\Mcp\Tool\BuiltinTools;
use Altair\Mcp\Tool\ContainerToolResolver;
use Altair\Mcp\Tool\ToolRegistry;
use Altair\Scaffold\Journal\Configuration\ScaffoldJournalConfiguration;
use Altair\Scaffold\Journal\Journal;
use Cycle\Database\DatabaseProviderInterface;
use Override;

/**
 * Wires the MCP server into the Container: the tool registry (built-in tools +
 * any user tool directories), the protocol services, the guardrails, and the
 * read-only database gateway. Prerequisite framework Configurations (events,
 * scaffold journal, doctor) are applied when absent so tool dependencies
 * autowire — exactly as they do for the equivalent CLI commands.
 */
final readonly class McpConfiguration implements ConfigurationInterface
{
    /**
     * @param list<string> $userToolPaths directories scanned for #[McpTool] classes
     */
    public function __construct(
        private ?string $projectRoot = null,
        private ServerMode $mode = new ServerMode(),
        private array $userToolPaths = [],
    ) {}

    #[Override]
    public function apply(Container $container): void
    {
        $root = $this->projectRoot ?? (getcwd() ?: '.');
        $context = ProjectContext::detect($root);

        $this->applyPrerequisites($container, $root);

        $userToolPaths = $this->userToolPaths;

        $container->instance($container::class, $container);
        $container->instance($context::class, $context);
        $container->instance($this->mode::class, $this->mode);

        $pathGuard = new PathGuard($context->projectRoot);
        $container->instance($pathGuard::class, $pathGuard);
        $serverInfo = new ServerInfo();
        $container->instance($serverInfo::class, $serverInfo);
        $schemaValidator = new SchemaValidator();
        $container->instance($schemaValidator::class, $schemaValidator);

        $container->factory(
            EventLog::class,
            static fn(?RecorderInterface $recorder = null): EventLog => new EventLog($recorder),
        )->shared();

        $container->factory(
            DatabaseGatewayInterface::class,
            static function (Container $c): DatabaseGatewayInterface {
                if (interface_exists(DatabaseProviderInterface::class) && $c->has(DatabaseProviderInterface::class)) {
                    $provider = $c->get(DatabaseProviderInterface::class);
                    if ($provider instanceof DatabaseProviderInterface) {
                        return new CycleDatabaseGateway($provider);
                    }
                }

                return new NullDatabaseGateway();
            },
        )->shared();

        $container->factory(
            ToolResolverInterface::class,
            static fn(Container $c): ToolResolverInterface => new ContainerToolResolver($c),
        )->shared();

        $container->factory(
            ToolRegistry::class,
            static function () use ($userToolPaths): ToolRegistry {
                $discoverer = new AttributeToolDiscoverer();
                $registry = new ToolRegistry();

                $classes = [...BuiltinTools::classes(), ...$discoverer->discoverClasses($userToolPaths)];
                foreach ($discoverer->fromClasses($classes) as $descriptor) {
                    if (!$registry->has($descriptor->name)) {
                        $registry->register($descriptor);
                    }
                }

                return $registry;
            },
        )->shared();

        $container->factory(
            Server::class,
            static fn(
                ToolRegistry $registry,
                ToolResolverInterface $resolver,
                SchemaValidator $validator,
                ServerInfo $info,
            ): Server => new Server($registry, $resolver, $validator, $info),
        )->shared();
    }

    private function applyPrerequisites(Container $container, string $root): void
    {
        if (!$container->has(RecorderInterface::class)) {
            (new EventsConfiguration($root))->apply($container);
        }

        if (!$container->has(Journal::class)) {
            (new ScaffoldJournalConfiguration($root))->apply($container);
        }

        if (!$container->has(Doctor::class)) {
            (new DoctorConfiguration($root))->apply($container);
        }
    }
}
