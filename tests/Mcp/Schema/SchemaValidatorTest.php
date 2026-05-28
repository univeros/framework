<?php

declare(strict_types=1);

namespace Altair\Tests\Mcp\Schema;

use Altair\Mcp\Schema\SchemaValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaValidator::class)]
final class SchemaValidatorTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'count' => ['type' => 'integer'],
            ],
            'required' => ['name'],
            'additionalProperties' => false,
        ];
    }

    public function testNullSchemaAlwaysPasses(): void
    {
        $result = (new SchemaValidator())->validate(['anything' => 1], null);

        self::assertTrue($result->valid);
    }

    public function testValidInputPasses(): void
    {
        $result = (new SchemaValidator())->validate(['name' => 'x', 'count' => 2], $this->schema());

        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
    }

    public function testMissingRequiredPropertyFails(): void
    {
        $result = (new SchemaValidator())->validate(['count' => 2], $this->schema());

        self::assertFalse($result->valid);
        self::assertNotSame([], $result->errors);
    }

    public function testWrongTypeFails(): void
    {
        $result = (new SchemaValidator())->validate(['name' => 'x', 'count' => 'not-int'], $this->schema());

        self::assertFalse($result->valid);
    }

    public function testEmptyInputValidatesAgainstObjectSchema(): void
    {
        // Empty args decode to [] in PHP; the validator must treat them as {}.
        $result = (new SchemaValidator())->validate([], ['type' => 'object']);

        self::assertTrue($result->valid);
    }

    public function testAdditionalPropertyRejected(): void
    {
        $result = (new SchemaValidator())->validate(['name' => 'x', 'extra' => 1], $this->schema());

        self::assertFalse($result->valid);
    }
}
