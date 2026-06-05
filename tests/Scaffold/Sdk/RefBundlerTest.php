<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Sdk;

use Altair\Scaffold\Sdk\Model\RefBundler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;

use Altair\Scaffold\Sdk\Model\BundleResult;

#[CoversClass(RefBundler::class)]
#[CoversClass(BundleResult::class)]
final class RefBundlerTest extends TestCase
{
    private string $sandbox = '';

    protected function setUp(): void
    {
        $this->sandbox = sys_get_temp_dir() . '/altair-bundler-' . bin2hex(random_bytes(4));
        mkdir($this->sandbox, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->sandbox)) {
            $this->removeRecursively($this->sandbox);
        }
    }

    public function testDocumentWithoutExternalRefsIsUnchanged(): void
    {
        $document = ['openapi' => '3.1.0', 'components' => ['schemas' => ['Pet' => ['type' => 'object']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertSame($document, $result->document);
        self::assertSame([], $result->warnings);
    }

    public function testInlinesExternalSchemaAndRewritesRef(): void
    {
        $this->write('pet.yaml', "Pet:\n  type: object\n  required: [name]\n  properties:\n    name: { type: string }\n");
        $body = ['content' => ['application/json' => ['schema' => ['$ref' => './pet.yaml#/Pet']]]];
        $document = ['paths' => ['/pets' => ['post' => ['requestBody' => $body]]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertSame([], $result->warnings);
        $schema = $result->document['paths']['/pets']['post']['requestBody']['content']['application/json']['schema'];
        self::assertSame(['$ref' => '#/components/schemas/Pet'], $schema);
        self::assertSame('object', $result->document['components']['schemas']['Pet']['type']);
        self::assertSame(['name'], $result->document['components']['schemas']['Pet']['required']);
    }

    public function testResolvesNestedExternalRefAcrossFiles(): void
    {
        $this->write('pet.yaml', "Pet:\n  type: object\n  properties:\n    category: { \$ref: './category.yaml#/Category' }\n");
        $this->write('category.yaml', "Category:\n  type: object\n  properties:\n    name: { type: string }\n");
        $document = ['components' => ['schemas' => ['PetRef' => ['$ref' => './pet.yaml#/Pet']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertSame([], $result->warnings);
        $schemas = $result->document['components']['schemas'];
        self::assertSame(['$ref' => '#/components/schemas/Pet'], $schemas['PetRef']);
        self::assertSame(['$ref' => '#/components/schemas/Category'], $schemas['Pet']['properties']['category']);
        self::assertSame('string', $schemas['Category']['properties']['name']['type']);
    }

    public function testRewritesInternalRefInsideExternalFile(): void
    {
        // pet.yaml#/Pet references pet.yaml#/Tag via a same-file internal ref.
        $this->write('pet.yaml', "Pet:\n  type: object\n  properties:\n    tag: { \$ref: '#/Tag' }\nTag:\n  type: object\n  properties:\n    label: { type: string }\n");
        $document = ['components' => ['schemas' => ['PetRef' => ['$ref' => './pet.yaml#/Pet']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertSame([], $result->warnings);
        $schemas = $result->document['components']['schemas'];
        self::assertSame(['$ref' => '#/components/schemas/Tag'], $schemas['Pet']['properties']['tag']);
        self::assertSame('string', $schemas['Tag']['properties']['label']['type']);
    }

    public function testCyclicExternalRefsTerminate(): void
    {
        $this->write('a.yaml', "A:\n  type: object\n  properties:\n    b: { \$ref: './b.yaml#/B' }\n");
        $this->write('b.yaml', "B:\n  type: object\n  properties:\n    a: { \$ref: './a.yaml#/A' }\n");
        $document = ['components' => ['schemas' => ['Root' => ['$ref' => './a.yaml#/A']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertSame([], $result->warnings);
        $schemas = $result->document['components']['schemas'];
        self::assertSame(['$ref' => '#/components/schemas/B'], $schemas['A']['properties']['b']);
        self::assertSame(['$ref' => '#/components/schemas/A'], $schemas['B']['properties']['a']);
    }

    public function testDedupesNameAgainstExistingComponent(): void
    {
        $this->write('pet.yaml', "Pet:\n  type: object\n  properties:\n    external: { type: boolean }\n");
        $document = ['components' => ['schemas' => [
            'Pet' => ['type' => 'object', 'properties' => ['local' => ['type' => 'string']]],
            'Ref' => ['$ref' => './pet.yaml#/Pet'],
        ]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        $schemas = $result->document['components']['schemas'];
        // Existing Pet is untouched; the external one is bundled as Pet2.
        self::assertArrayHasKey('local', $schemas['Pet']['properties']);
        self::assertSame(['$ref' => '#/components/schemas/Pet2'], $schemas['Ref']);
        self::assertArrayHasKey('external', $schemas['Pet2']['properties']);
    }

    public function testRejectsRemoteRef(): void
    {
        $document = ['components' => ['schemas' => ['R' => ['$ref' => 'https://evil.example/p.yaml#/Pet']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('remote', $result->warnings[0]);
        // Left untouched so the mapper still surfaces it.
        self::assertSame(['$ref' => 'https://evil.example/p.yaml#/Pet'], $result->document['components']['schemas']['R']);
    }

    public function testRejectsPathTraversalEscapingBaseDir(): void
    {
        // A file that physically exists outside the base dir must not be read.
        $outside = \dirname($this->sandbox) . '/altair-bundler-secret-' . bin2hex(random_bytes(4)) . '.yaml';
        file_put_contents($outside, "Secret:\n  type: object\n");
        try {
            $document = ['components' => ['schemas' => ['R' => ['$ref' => '../' . basename($outside) . '#/Secret']]]];

            $result = (new RefBundler($this->sandbox))->bundle($document);

            self::assertCount(1, $result->warnings);
            self::assertStringContainsString('escapes the document directory', $result->warnings[0]);
            self::assertArrayNotHasKey('Secret', $result->document['components']['schemas']);
        } finally {
            @unlink($outside);
        }
    }

    public function testRejectsAbsolutePath(): void
    {
        $document = ['components' => ['schemas' => ['R' => ['$ref' => '/etc/passwd#/x']]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('absolute', $result->warnings[0]);
    }

    public function testWarnsOnMissingFileAndUnknownPointer(): void
    {
        $this->write('pet.yaml', "Pet:\n  type: object\n");
        $document = ['components' => ['schemas' => [
            'Gone' => ['$ref' => './missing.yaml#/Pet'],
            'NoPointer' => ['$ref' => './pet.yaml#/Nope'],
        ]]];

        $result = (new RefBundler($this->sandbox))->bundle($document);

        self::assertCount(2, $result->warnings);
        self::assertStringContainsString('was not found', $result->warnings[0]);
        self::assertStringContainsString('does not resolve to a schema', $result->warnings[1]);
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->sandbox . '/' . $name, $contents);
    }

    private function removeRecursively(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.') {
                continue;
            }

            if ($entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeRecursively($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
