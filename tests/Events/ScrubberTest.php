<?php

declare(strict_types=1);

namespace Altair\Tests\Events;

use Altair\Events\Scrubber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scrubber::class)]
class ScrubberTest extends TestCase
{
    #[DataProvider('scrubCases')]
    public function testScrubsKnownSecretFlags(string $input, string $expected): void
    {
        $this->assertSame($expected, (new Scrubber())->scrub($input));
    }

    public static function scrubCases(): iterable
    {
        yield 'equals form' => [
            'bin/altair db:migrate --password=hunter2',
            'bin/altair db:migrate --password=***',
        ];

        yield 'space form' => [
            'bin/altair worker --token abc123',
            'bin/altair worker --token ***',
        ];

        yield 'mixed case' => [
            'bin/altair foo --API-KEY=zzz',
            'bin/altair foo --API-KEY=***',
        ];

        yield 'multiple secrets' => [
            'bin/altair foo --password=a --token=b',
            'bin/altair foo --password=*** --token=***',
        ];

        yield 'non-secret flag untouched' => [
            'bin/altair foo --port=8080',
            'bin/altair foo --port=8080',
        ];

        yield 'no-op when no secrets' => [
            'bin/altair help',
            'bin/altair help',
        ];
    }

    public function testCustomSecretsAreAdditive(): void
    {
        $scrubbed = (new Scrubber())
            ->withSecrets(['--my-custom-secret'])
            ->scrub('bin/altair foo --my-custom-secret=zzz --password=yyy');

        $this->assertSame('bin/altair foo --my-custom-secret=*** --password=***', $scrubbed);
    }

    public function testDoesNotOverRedactSimilarNames(): void
    {
        // --password-policy is NOT a secret flag; --password is.
        $scrubbed = (new Scrubber())->scrub('bin/altair foo --password-policy=strict --password=hunter2');

        $this->assertStringContainsString('--password-policy=strict', $scrubbed);
        $this->assertStringContainsString('--password=***', $scrubbed);
    }
}
