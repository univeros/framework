<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold;

use Altair\Scaffold\Cli\OpenApiImportOptions;
use Altair\Scaffold\Cli\OpenApiImportRunner;
use Altair\Scaffold\Emitter\Naming;
use Altair\Scaffold\Emitter\OpenApiEmitter;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Model\OperationModel;
use Altair\Scaffold\Spec\Ast\EndpointSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\Emitter\OperationMapper;
use Altair\Scaffold\Spec\Emitter\PathDeriver;
use Altair\Scaffold\Spec\Emitter\ResourceGrouping;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * #227: `endpoint.resource` nests the HTTP triple into per-resource folders;
 * `openapi:import --group-by` fills it; `x-altair-resource` round-trips it.
 */
#[CoversClass(Naming::class)]
#[CoversClass(PathDeriver::class)]
#[CoversClass(OperationMapper::class)]
#[CoversClass(Validator::class)]
#[CoversClass(Parser::class)]
#[CoversClass(OpenApiEmitter::class)]
final class ResourceGroupingTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-resource-grouping-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->sandbox));
    }

    public function testNamingStaysFlatWithoutResource(): void
    {
        $naming = new Naming();
        $spec = SpecFixture::createUser();

        self::assertSame('App\\Http\\Actions\\CreateUserAction', $naming->actionFqcn($spec));
        self::assertSame('app/Http/Actions/CreateUserAction.php', $naming->actionPath($spec));
        self::assertSame('Tests\\Http\\Actions', $naming->testNamespace($spec));
        self::assertSame('tests/Http/Actions/CreateUserActionTest.php', $naming->testPath($spec));
    }

    public function testNamingNestsTheHttpTripleAndTestUnderTheResource(): void
    {
        $naming = new Naming();
        $spec = $this->withResource(SpecFixture::createUser(), 'Account');

        self::assertSame('App\\Http\\Actions\\Account\\CreateUserAction', $naming->actionFqcn($spec));
        self::assertSame('App\\Http\\Inputs\\Account\\CreateUserInput', $naming->inputFqcn($spec));
        self::assertSame('App\\Http\\Responders\\Account\\CreateUserResponder', $naming->responderFqcn($spec));
        self::assertSame('app/Http/Actions/Account/CreateUserAction.php', $naming->actionPath($spec));
        self::assertSame('app/Http/Inputs/Account/CreateUserInput.php', $naming->inputPath($spec));
        self::assertSame('app/Http/Responders/Account/CreateUserResponder.php', $naming->responderPath($spec));
        self::assertSame('Tests\\Http\\Actions\\Account', $naming->testNamespace($spec));
        self::assertSame('tests/Http/Actions/Account/CreateUserActionTest.php', $naming->testPath($spec));
        // The domain class stays author-chosen.
        self::assertSame('App\\User\\CreateUser', $naming->domainFqcn($spec));
    }

    public function testParserReadsTheResource(): void
    {
        $spec = (new Parser())->parseString(<<<'YAML'
            endpoint: { method: GET, path: /users, resource: User }
            domain: { class: App\User\ListUsers }
            YAML);

        self::assertSame('User', $spec->endpoint->resource);
    }

    #[DataProvider('invalidResources')]
    public function testValidatorRejectsResourcesThatAreNotOnePascalCaseIdentifier(string $resource): void
    {
        $errors = (new Validator())->collectErrors($this->withResource(SpecFixture::createUser(), $resource));

        self::assertCount(1, $errors);
        self::assertStringContainsString('endpoint.resource', $errors[0]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidResources(): iterable
    {
        yield 'lowercase' => ['user'];
        yield 'nested' => ['User\\Admin'];
        yield 'path' => ['../User'];
        yield 'leading digit' => ['2Fa'];
        yield 'space' => ['Pet Store'];
    }

    public function testValidatorAcceptsAPascalCaseResource(): void
    {
        self::assertSame([], (new Validator())->collectErrors($this->withResource(SpecFixture::createUser(), 'PetStore')));
    }

    /**
     * @param list<string>         $tags
     * @param array<string, mixed> $extensions
     */
    #[DataProvider('derivations')]
    public function testResourceDerivation(string $path, array $tags, array $extensions, ResourceGrouping $grouping, string $expected): void
    {
        $operation = new OperationModel('op', 'GET', $path, [], null, [], extensions: $extensions, tags: $tags);

        self::assertSame($expected, (new PathDeriver())->resource($operation, $grouping));
    }

    /**
     * @return iterable<string, array{string, list<string>, array<string, mixed>, ResourceGrouping, string}>
     */
    public static function derivations(): iterable
    {
        yield 'none stays flat' => ['/pet/findByStatus', ['pet'], [], ResourceGrouping::None, ''];
        yield 'tag' => ['/pet/findByStatus', ['pet'], [], ResourceGrouping::Tag, 'Pet'];
        yield 'first tag wins' => ['/x', ['store', 'pet'], [], ResourceGrouping::Tag, 'Store'];
        yield 'tag singular pascal' => ['/x', ['pet-stores'], [], ResourceGrouping::Tag, 'PetStore'];
        yield 'tag with spaces' => ['/x', ['User Accounts'], [], ResourceGrouping::Tag, 'UserAccount'];
        yield 'no tag falls back to path' => ['/users/{id}', [], [], ResourceGrouping::Tag, 'User'];
        yield 'unusable tag falls back to path' => ['/users', ['2fa'], [], ResourceGrouping::Tag, 'User'];
        yield 'path noun skips rpc leaf' => ['/pet/findByStatus', ['store'], [], ResourceGrouping::Path, 'Pet'];
        yield 'x-altair-resource wins over none' => ['/users', [], ['x-altair-resource' => 'Auth'], ResourceGrouping::None, 'Auth'];
        yield 'x-altair-resource wins over tag' => ['/users', ['user'], ['x-altair-resource' => 'Auth'], ResourceGrouping::Tag, 'Auth'];
    }

    public function testResourceRoundTripsThroughOpenApiEvenWhenItDiffersFromTheTag(): void
    {
        $spec = $this->withResource(SpecFixture::createUser(), 'Account');

        $fragment = (new OpenApiEmitter())->emit($spec)->contents;
        self::assertStringContainsString('x-altair-resource: Account', $fragment);

        $document = (new OpenApiParser())->parseYaml($fragment);
        $endpoint = (new OperationMapper())->map($document, $document->operations[0])['endpoint'];

        self::assertSame('Account', $endpoint['resource']);
        self::assertSame(['users'], $endpoint['tags']);
    }

    public function testImportKeepsTheOperationTagsAndAddsNoResourceByDefault(): void
    {
        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $this->writePetstore(),
            projectRoot: $this->sandbox,
        ));

        self::assertTrue($receipt->ok, (string) $receipt->error);
        $spec = Yaml::parseFile($this->sandbox . '/api/pet/find.yaml');
        self::assertSame(['pet', 'pets'], $spec['endpoint']['tags']);
        self::assertArrayNotHasKey('resource', $spec['endpoint']);
    }

    public function testImportGroupedByTagScaffoldsPerResourceFolders(): void
    {
        $receipt = (new OpenApiImportRunner())->run(new OpenApiImportOptions(
            documentPath: $this->writePetstore(),
            projectRoot: $this->sandbox,
            scaffold: true,
            groupBy: ResourceGrouping::Tag,
        ));

        self::assertTrue($receipt->ok, (string) $receipt->error);
        foreach ([
            'app/Http/Actions/Pet/FindPetsByStatusAction.php',
            'app/Http/Inputs/Pet/FindPetsByStatusInput.php',
            'app/Http/Responders/Pet/FindPetsByStatusResponder.php',
            'tests/Http/Actions/Pet/FindPetsByStatusActionTest.php',
            'app/Http/Actions/Store/GetInventoryAction.php',
            'app/Http/Actions/User/CreateUserAction.php',
            'app/Http/Actions/Order/GetOrderAction.php', // untagged: path fallback
        ] as $path) {
            self::assertContains($path, $receipt->scaffolded);
        }

        $action = (string) file_get_contents($this->sandbox . '/app/Http/Actions/Pet/FindPetsByStatusAction.php');
        self::assertStringContainsString('namespace App\\Http\\Actions\\Pet;', $action);
        self::assertStringContainsString('use App\\Http\\Inputs\\Pet\\FindPetsByStatusInput;', $action);

        $test = (string) file_get_contents($this->sandbox . '/tests/Http/Actions/Pet/FindPetsByStatusActionTest.php');
        self::assertStringContainsString('namespace Tests\\Http\\Actions\\Pet;', $test);

        $routes = (string) file_get_contents($this->sandbox . '/config/routes.php');
        self::assertStringContainsString('App\\Http\\Actions\\Pet\\FindPetsByStatusAction', $routes);
    }

    public function testGroupedImportIsByteStable(): void
    {
        $document = $this->writePetstore();
        $run = function () use ($document): string {
            (new OpenApiImportRunner())->run(new OpenApiImportOptions(
                documentPath: $document,
                projectRoot: $this->sandbox,
                force: true,
                groupBy: ResourceGrouping::Tag,
            ));

            return (string) file_get_contents($this->sandbox . '/api/pet/find.yaml');
        };

        self::assertSame($run(), $run());
    }

    private function withResource(Spec $spec, string $resource): Spec
    {
        $e = $spec->endpoint;

        return new Spec(
            endpoint: new EndpointSpec($e->method, $e->path, $e->summary, $e->tags, $resource),
            inputs: $spec->inputs,
            outputs: $spec->outputs,
            domain: $spec->domain,
            sourcePath: $spec->sourcePath,
        );
    }

    private function writePetstore(): string
    {
        $path = $this->sandbox . '/openapi.yaml';
        file_put_contents($path, <<<'YAML'
            openapi: 3.1.0
            info: { title: Petstore, version: 1.0.0 }
            paths:
              /pet/findByStatus:
                get:
                  operationId: findPetsByStatus
                  tags: [pet, pets]
                  responses: { '200': { description: ok } }
              /store/inventory:
                get:
                  operationId: getInventory
                  tags: [store]
                  responses: { '200': { description: ok } }
              /user:
                post:
                  operationId: createUser
                  tags: [user]
                  responses: { '201': { description: ok } }
              /orders/{id}:
                get:
                  operationId: getOrder
                  responses: { '200': { description: ok } }
            YAML);

        return $path;
    }
}
