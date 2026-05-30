<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Tests\Examples\Library;

use Altair\Examples\Library\ExampleParser;
use Altair\Examples\Library\Exception\InvalidFrontmatterException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleParser::class)]
#[CoversClass(InvalidFrontmatterException::class)]
final class ExampleParserTest extends TestCase
{
    public function testParsesAWellFormedExample(): void
    {
        $source = <<<MD
        ---
        title: Endpoint that does a thing
        scenario: Show how to do the thing.
        packages: [http, persistence]
        since: 2.0.0
        tested_by: tests/Examples/HttpDoesThingTest.php
        ---
        # Endpoint that does a thing

        Some prose describing the example.

        ```php
        echo 'hi';
        ```
        MD;

        $example = (new ExampleParser())->parse('http/does-thing', $source);

        self::assertSame('http/does-thing', $example->id);
        self::assertSame('Endpoint that does a thing', $example->title);
        self::assertSame('Show how to do the thing.', $example->scenario);
        self::assertSame(['http', 'persistence'], $example->packages);
        self::assertSame('2.0.0', $example->since);
        self::assertSame('tests/Examples/HttpDoesThingTest.php', $example->testedBy);
        self::assertStringContainsString('# Endpoint that does a thing', $example->body);
        self::assertStringContainsString("```php\necho 'hi';\n```", $example->body);
    }

    public function testRejectsMissingOpeningDelimiter(): void
    {
        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('missing the YAML frontmatter delimiters');

        (new ExampleParser())->parse('bad', "title: foo\n\n# Body");
    }

    public function testRejectsMissingClosingDelimiter(): void
    {
        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('missing the YAML frontmatter delimiters');

        (new ExampleParser())->parse('bad', "---\ntitle: foo\n# never closes");
    }

    public function testRejectsMalformedYaml(): void
    {
        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('malformed frontmatter YAML');

        (new ExampleParser())->parse('bad', "---\ntitle: [unbalanced\n---\nbody");
    }

    public function testRejectsMissingRequiredField(): void
    {
        $source = <<<MD
        ---
        title: Has title
        scenario: but missing other things
        packages: [http]
        since: 2.0.0
        ---
        body
        MD;

        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('missing required frontmatter field "tested_by"');

        (new ExampleParser())->parse('bad', $source);
    }

    public function testRejectsPackagesThatIsNotAList(): void
    {
        $source = <<<MD
        ---
        title: t
        scenario: s
        packages:
          http: yes
        since: 2.0.0
        tested_by: tests/T.php
        ---
        body
        MD;

        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('"packages" must be a list of strings');

        (new ExampleParser())->parse('bad', $source);
    }

    public function testRejectsBlankRequiredField(): void
    {
        $source = <<<MD
        ---
        title: "   "
        scenario: s
        packages: [http]
        since: 2.0.0
        tested_by: tests/T.php
        ---
        body
        MD;

        $this->expectException(InvalidFrontmatterException::class);
        $this->expectExceptionMessage('"title" must be a non-empty string');

        (new ExampleParser())->parse('bad', $source);
    }

    public function testAcceptsCrlfLineEndings(): void
    {
        $source = "---\r\ntitle: x\r\nscenario: y\r\npackages: [http]\r\nsince: 2.0.0\r\ntested_by: tests/T.php\r\n---\r\nhello";

        $example = (new ExampleParser())->parse('crlf', $source);

        self::assertSame('x', $example->title);
        self::assertStringContainsString('hello', $example->body);
    }

    public function testHandlesEmptyBody(): void
    {
        $source = "---\ntitle: t\nscenario: s\npackages: [http]\nsince: 2.0.0\ntested_by: tests/T.php\n---\n";

        $example = (new ExampleParser())->parse('empty-body', $source);

        self::assertSame('', $example->body);
    }
}
