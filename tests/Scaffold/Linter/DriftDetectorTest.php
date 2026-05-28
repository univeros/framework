<?php

declare(strict_types=1);

namespace Altair\Tests\Scaffold\Linter;

use Altair\Scaffold\Linter\DriftFinding;
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Linter\DriftDetector;
use Altair\Scaffold\Linter\DriftKind;
use Altair\Scaffold\Writer\FileWriter;
use Altair\Tests\Scaffold\Support\SpecFixture;
use PHPUnit\Framework\TestCase;

final class DriftDetectorTest extends TestCase
{
    private string $tempRoot;

    #[\Override]
    protected function setUp(): void
    {
        $this->tempRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scaffold-drift-' . bin2hex(random_bytes(4));
        mkdir($this->tempRoot, 0o755, true);

        $spec = SpecFixture::createUser();
        $writer = new FileWriter($this->tempRoot);
        foreach ((new EmissionPlan())->build($spec) as $file) {
            $writer->write($file, false);
        }
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tempRoot)) {
            $this->removeDirectory($this->tempRoot);
        }
    }

    public function testFreshlyScaffoldedFilesReportNoDrift(): void
    {
        $report = (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser());

        self::assertFalse($report->hasDrift(), implode("\n", array_map(static fn (DriftFinding $f): string => $f->message, $report->findings)));
    }

    public function testMissingFieldDetected(): void
    {
        $inputPath = $this->tempRoot . '/app/Http/Inputs/CreateUserInput.php';
        $this->rewriteInputWithFields($inputPath, ['email']);

        $report = (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser());

        self::assertTrue($report->hasDrift());
        $kinds = array_map(static fn (DriftFinding $f): DriftKind => $f->kind, $report->findings);
        self::assertContains(DriftKind::MissingInputField, $kinds);
    }

    /**
     * Rewrites the input DTO with the given list of fields. Keeps the file
     * syntactically valid so the detector parses it cleanly.
     *
     * @param list<string> $fields
     */
    private function rewriteInputWithFields(string $path, array $fields): void
    {
        $params = [];
        foreach ($fields as $field) {
            $params[] = sprintf('        public string $%s,', $field);
        }

        $params[] = '    ) {}';
        $ctor = "    public function __construct(\n" . implode("\n", $params);

        $contents = <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Http\\Inputs;

            final readonly class CreateUserInput
            {
            {$ctor}

                public static function rules(): array
                {
                    return [];
                }
            }

            PHP;

        file_put_contents($path, $contents);
    }

    public function testUnknownFieldDetected(): void
    {
        $inputPath = $this->tempRoot . '/app/Http/Inputs/CreateUserInput.php';
        $contents = (string) file_get_contents($inputPath);
        $patched = str_replace(
            'public string $email,',
            "public string \$email,\n        public string \$extraField,",
            $contents,
        );
        file_put_contents($inputPath, $patched);

        $kinds = array_map(
            static fn (DriftFinding $f): DriftKind => $f->kind,
            (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser())->findings,
        );
        self::assertContains(DriftKind::UnknownInputField, $kinds);
    }

    public function testMissingRuleDetected(): void
    {
        $inputPath = $this->tempRoot . '/app/Http/Inputs/CreateUserInput.php';
        $contents = (string) file_get_contents($inputPath);
        $patched = str_replace("'email', 'required'", "'required'", $contents);
        file_put_contents($inputPath, $patched);

        $kinds = array_map(
            static fn (DriftFinding $f): DriftKind => $f->kind,
            (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser())->findings,
        );
        self::assertContains(DriftKind::MissingValidationRule, $kinds);
    }

    public function testMissingResponderStatusDetected(): void
    {
        $responderPath = $this->tempRoot . '/app/Http/Responders/CreateUserResponder.php';
        $contents = (string) file_get_contents($responderPath);
        $patched = str_replace('return [201, 409, 422];', 'return [201, 409];', $contents);
        file_put_contents($responderPath, $patched);

        $kinds = array_map(
            static fn (DriftFinding $f): DriftKind => $f->kind,
            (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser())->findings,
        );
        self::assertContains(DriftKind::ResponderMissingStatus, $kinds);
    }

    public function testUnregisteredRouteDetected(): void
    {
        unlink($this->tempRoot . '/config/routes.php');

        $kinds = array_map(
            static fn (DriftFinding $f): DriftKind => $f->kind,
            (new DriftDetector($this->tempRoot))->detect(SpecFixture::createUser())->findings,
        );
        self::assertContains(DriftKind::UnregisteredRoute, $kinds);
    }

    private function removeDirectory(string $path): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
