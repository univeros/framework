<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Cli;

use Altair\Scaffold\Cli\ImportReceipt;
use PHPUnit\Framework\TestCase;

final class ImportReceiptTest extends TestCase
{
    public function testToArrayShapeMatchesContract(): void
    {
        $receipt = new ImportReceipt(
            ok: true,
            input: 'openapi.yaml',
            specsWritten: ['api/users/create.yaml'],
            scaffoldRequested: true,
            scaffolded: ['app/Http/Actions/CreateUserAction.php'],
            rolledBack: [],
            unmapped: [],
            warnings: ['queue=redis recorded but inert'],
            journalId: null,
            eventId: null,
            error: null,
        );

        self::assertSame(
            [
                'ok' => true,
                'input' => 'openapi.yaml',
                'specs_written' => ['api/users/create.yaml'],
                'scaffolded' => true,
                'scaffold_files' => ['app/Http/Actions/CreateUserAction.php'],
                'rolled_back' => [],
                'unmapped' => [],
                'warnings' => ['queue=redis recorded but inert'],
                'journal_id' => null,
                'event_id' => null,
                'error' => null,
            ],
            $receipt->toArray(),
        );
    }

    public function testJsonIsByteStableWithNullIds(): void
    {
        $first = (new ImportReceipt(
            ok: true,
            input: 'in.yaml',
            specsWritten: ['a.yaml'],
            scaffoldRequested: false,
            scaffolded: [],
            rolledBack: [],
            unmapped: [],
            warnings: [],
            journalId: null,
            eventId: null,
            error: null,
        ))->toJson();

        $second = (new ImportReceipt(
            ok: true,
            input: 'in.yaml',
            specsWritten: ['a.yaml'],
            scaffoldRequested: false,
            scaffolded: [],
            rolledBack: [],
            unmapped: [],
            warnings: [],
            journalId: null,
            eventId: null,
            error: null,
        ))->toJson();

        self::assertSame($first, $second);
    }

    public function testFailureReceiptCarriesError(): void
    {
        $receipt = new ImportReceipt(
            ok: false,
            input: 'broken.yaml',
            specsWritten: [],
            scaffoldRequested: false,
            scaffolded: [],
            rolledBack: [],
            unmapped: [['pointer' => '#/paths/~1x/post', 'message' => 'unmappable']],
            warnings: [],
            journalId: null,
            eventId: null,
            error: 'unmappable schema',
        );

        $array = $receipt->toArray();
        self::assertFalse($array['ok']);
        self::assertSame('unmappable schema', $array['error']);
        self::assertCount(1, $array['unmapped']);
        self::assertSame('#/paths/~1x/post', $array['unmapped'][0]['pointer']);
    }
}
