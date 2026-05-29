<?php

declare(strict_types=1);

namespace Altair\Tests\Cli;

use Altair\Cli\AltairCommand;
use Altair\Cli\Application;
use Altair\Cli\Configuration\CliConfiguration;
use Altair\Container\Container;
use Altair\Tests\Cli\Fixture\CreateUserIntegrationCommand;
use Altair\Tests\Cli\Fixture\Role;
use Altair\Tests\Cli\Fixture\SpyUserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Tester\CommandTester;

class IntegrationTest extends TestCase
{
    public function testCommandIsInvokedWithCoercedArguments(): void
    {
        [$container, $repository] = $this->buildContainer();
        $command = new AltairCommand(CreateUserIntegrationCommand::class, $container);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'email' => 'jane@example.com',
            '--password' => 's3cret',
            '--role' => Role::Admin->value,
            '--silent' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertCount(1, $repository->calls);
        $this->assertSame('jane@example.com', $repository->calls[0]['email']);
        $this->assertSame('s3cret', $repository->calls[0]['password']);
        $this->assertSame(Role::Admin, $repository->calls[0]['role']);
        $this->assertTrue($repository->calls[0]['silent']);
    }

    public function testOptionsFallBackToDefaults(): void
    {
        [$container, $repository] = $this->buildContainer();
        $command = new AltairCommand(CreateUserIntegrationCommand::class, $container);

        $tester = new CommandTester($command);
        $tester->execute(['email' => 'pat@example.com']);

        $this->assertCount(1, $repository->calls);
        $this->assertNull($repository->calls[0]['password']);
        $this->assertSame(Role::Member, $repository->calls[0]['role']);
        $this->assertFalse($repository->calls[0]['silent']);
    }

    public function testInvalidEnumProducesCleanSymfonyConsoleError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a valid case of enum/i');

        [$container] = $this->buildContainer();
        $command = new AltairCommand(CreateUserIntegrationCommand::class, $container);

        $tester = new CommandTester($command);
        $tester->execute([
            'email' => 'jane@example.com',
            '--role' => 'super-admin',
        ]);
    }

    public function testApplicationAutoDiscoversCommands(): void
    {
        $container = new Container();
        $configuration = new CliConfiguration([__DIR__ . '/Discovery/fixtures']);
        $configuration->apply($container);

        $application = $container->make(Application::class);
        $application->setAutoExit(false);

        $this->assertTrue($application->has('fixture:create-user'));
        $this->assertTrue($application->has('fixture:nested'));
    }

    public function testHelpOutputContainsAttributeDescriptions(): void
    {
        [$container] = $this->buildContainer();
        $command = new AltairCommand(CreateUserIntegrationCommand::class, $container);
        $command->mergeApplicationDefinition();

        $emailArgument = $command->getDefinition()->getArgument('email');
        $passwordOption = $command->getDefinition()->getOption('password');

        $this->assertSame('The user email', $emailArgument->getDescription());
        $this->assertSame('Initial password (random if omitted)', $passwordOption->getDescription());
        $this->assertSame('Create a new user account', $command->getDescription());
        $this->assertContains('users:add', $command->getAliases());
        $this->assertSame('Detailed help block.', $command->getHelp());
    }

    /**
     * @return array{0: Container, 1: SpyUserRepository}
     */
    private function buildContainer(): array
    {
        $container = new Container();
        $repository = new SpyUserRepository();
        $container->instance($repository::class, $repository);

        return [$container, $repository];
    }
}
