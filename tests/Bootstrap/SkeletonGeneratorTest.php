<?php

declare(strict_types=1);

namespace Altair\Tests\Bootstrap;

use Altair\Bootstrap\Exception\BootstrapException;
use Altair\Bootstrap\SkeletonGenerator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SkeletonGenerator::class)]
final class SkeletonGeneratorTest extends TestCase
{
    private string $target;

    #[Override]
    protected function setUp(): void
    {
        $this->target = sys_get_temp_dir() . '/altair-skel-' . bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDir($this->target);
    }

    public function testDefaultSkeletonPathExists(): void
    {
        self::assertDirectoryExists(SkeletonGenerator::defaultSkeletonPath());
    }

    public function testGeneratesACompleteRunnableProject(): void
    {
        $created = (new SkeletonGenerator())->generate($this->target);

        self::assertContains('composer.json', $created);
        self::assertContains('public/index.php', $created);
        self::assertContains('app/Http/Actions/PingAction.php', $created);
        self::assertContains('app/Health/Ping.php', $created);
        self::assertContains('config/routes.php', $created);
        self::assertContains('api/ping.yaml', $created);
        // #131 — the skeleton ships the Altair agent skill at two paths so it's
        // discoverable by both the cross-agent Agent Skills spec (.ai/skills/)
        // and Claude Code's project-skills path (.claude/skills/). Both copies
        // travel through the generator unmodified.
        self::assertContains('.ai/skills/altair/SKILL.md', $created);
        self::assertContains('.claude/skills/altair/SKILL.md', $created);

        self::assertFileExists($this->target . '/.env.example');
        self::assertFileExists($this->target . '/.ai/skills/altair/SKILL.md');
        self::assertFileExists($this->target . '/.claude/skills/altair/SKILL.md');
    }

    public function testAiAndClaudeSkillCopiesAreByteEqual(): void
    {
        // #131 — the two skill paths must stay in lockstep; a drift here would
        // mean a Claude Code agent and a Junie-style agent on the same project
        // see different guidance. Asserted at the skeleton source (the file
        // committed to the repo) so a future hand-edit catches it before it
        // ships into generated projects.
        $base = \dirname(__DIR__, 2) . '/src/Altair/Bootstrap/resources/skeleton';
        $aiPath = $base . '/.ai/skills/altair/SKILL.md';
        $claudePath = $base . '/.claude/skills/altair/SKILL.md';

        self::assertFileExists($aiPath, '.ai/skills/altair/SKILL.md missing from the skeleton');
        self::assertFileExists($claudePath, '.claude/skills/altair/SKILL.md missing from the skeleton');
        self::assertSame(
            file_get_contents($aiPath),
            file_get_contents($claudePath),
            'the .ai/ and .claude/ copies of the Altair skill have drifted — keep them byte-equal',
        );
    }

    public function testSetsTheProjectNameInComposer(): void
    {
        (new SkeletonGenerator())->generate($this->target, projectName: 'acme/api');

        $composer = json_decode((string) file_get_contents($this->target . '/composer.json'), true);
        self::assertIsArray($composer);
        self::assertSame('acme/api', $composer['name']);
    }

    public function testRewritesNamespaceWhenCustomised(): void
    {
        (new SkeletonGenerator())->generate($this->target, namespace: 'Acme', projectName: 'acme/api');

        $action = (string) file_get_contents($this->target . '/app/Http/Actions/PingAction.php');
        self::assertStringContainsString('namespace Acme\\Http\\Actions;', $action);
        self::assertStringNotContainsString('namespace App\\', $action);

        $composer = json_decode((string) file_get_contents($this->target . '/composer.json'), true);
        self::assertIsArray($composer);
        self::assertArrayHasKey('Acme\\', $composer['autoload']['psr-4']);

        // Non-PHP files that reference App\ FQNs are rewritten too, so spec:lint /
        // spec:scaffold resolve the domain class in a custom-namespace project.
        $spec = (string) file_get_contents($this->target . '/api/ping.yaml');
        self::assertStringContainsString('Acme\\Health\\Ping', $spec);
    }

    public function testRejectsAnInvalidNamespace(): void
    {
        $this->expectException(BootstrapException::class);
        (new SkeletonGenerator())->generate($this->target, namespace: 'Foo; bad');
    }

    public function testRefusesToOverwriteNonEmptyTargetWithoutForce(): void
    {
        mkdir($this->target, 0o755, true);
        file_put_contents($this->target . '/keep.txt', 'mine');

        $this->expectException(BootstrapException::class);
        (new SkeletonGenerator())->generate($this->target);
    }

    public function testForceOverwritesExistingTarget(): void
    {
        mkdir($this->target, 0o755, true);
        file_put_contents($this->target . '/keep.txt', 'mine');

        $created = (new SkeletonGenerator())->generate($this->target, force: true);

        self::assertContains('composer.json', $created);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.') {
                continue;
            }

            if ($item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
